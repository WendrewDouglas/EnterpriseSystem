# %% 1. Importar Bibliotecas e Criar Conexão com o Banco
import sys
import os
import warnings
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
from datetime import datetime
from dateutil.relativedelta import relativedelta
import json

# Suprimir warnings indesejados (como SettingWithCopyWarning)
warnings.simplefilter(action='ignore', category=pd.errors.SettingWithCopyWarning)

# Adicionar o caminho para o módulo de conexão usando caminho relativo
current_dir = os.path.dirname(os.path.abspath(__file__))
root_dir = os.path.abspath(os.path.join(current_dir, ".."))
includes_path = os.path.join(root_dir, "includes")
if includes_path not in sys.path:
    sys.path.append(includes_path)

from db_connection import get_db_connection
engine = get_db_connection()

# %% 2. Executar Queries e Carregar Dados
query_faturamento = "SELECT * FROM V_FATURAMENTO"
query_forecast_modelo = "SELECT * FROM forecast_entries"
query_itens = "SELECT * FROM V_DEPARA_ITEM"
query_forecast_sku = "SELECT * FROM forecast_entries_sku"  # Registros já existentes

df_faturamento = pd.read_sql(query_faturamento, engine)
df_forecast = pd.read_sql(query_forecast_modelo, engine)
df_itens = pd.read_sql(query_itens, engine)
df_forecast_sku = pd.read_sql(query_forecast_sku, engine)

# %% 3. Conversão de Colunas de Data
df_faturamento['Data_Faturamento'] = pd.to_datetime(df_faturamento['Data_Faturamento'])
df_forecast['data_lancamento'] = pd.to_datetime(df_forecast['data_lancamento'])

# %% 4. Filtrar Quantidades Negativas no Faturamento
df_faturamento = df_faturamento[df_faturamento['Quantidade'] >= 0]

# %% 5. Converter o Campo mes_referencia do Forecast
df_forecast['mes_forecast_dt'] = pd.to_datetime(df_forecast['mes_referencia'], format='%m/%Y')\
                                  .dt.to_period('M').dt.to_timestamp()

# %% 6. Definir Períodos de Análise
data_atual = pd.Timestamp.today()
ultimo_mes_completo = data_atual.replace(day=1) - pd.Timedelta(days=1)
periodo_fim = ultimo_mes_completo
periodo_inicio = ultimo_mes_completo.replace(day=1) - relativedelta(months=2)
# Debug:
# print("Período dos últimos 3 meses (baseado na data atual):", periodo_inicio.date(), "a", periodo_fim.date())

if not df_forecast.empty:
    mes_forecast = df_forecast.loc[0, 'mes_forecast_dt']
else:
    raise ValueError("df_forecast está vazio.")
mes_anterior = mes_forecast - relativedelta(years=1)
mes_anterior_inicio = mes_anterior
mes_anterior_fim = (mes_anterior + relativedelta(months=1)) - pd.Timedelta(days=1)
# Debug:
# print("Período do mesmo mês do ano anterior (com base no mes forecast):", mes_anterior_inicio.date(), "a", mes_anterior_fim.date())

# %% 7. Filtrar o Faturamento com Base nos Períodos Definidos
df_faturamento_periodo = df_faturamento[
    ((df_faturamento['Data_Faturamento'] >= periodo_inicio) & (df_faturamento['Data_Faturamento'] <= periodo_fim)) |
    ((df_faturamento['Data_Faturamento'] >= mes_anterior_inicio) & (df_faturamento['Data_Faturamento'] <= mes_anterior_fim))
]
# Debug:
# print("Registros no período filtrado:", df_faturamento_periodo.shape)

# %% 8. Converter Chaves para String para Garantir Compatibilidade
df_faturamento_periodo['Cod_produto'] = df_faturamento_periodo['Cod_produto'].astype(str)
df_itens['CODITEM'] = df_itens['CODITEM'].astype(str)

# %% 9. Unir Faturamento com a Tabela de Itens (depara_item)
df_faturamento_merge = pd.merge(df_faturamento_periodo, df_itens,
                                left_on='Cod_produto', right_on='CODITEM', how='left')
# Debug:
# print("Merge - head:")
# print(df_faturamento_merge.head())
# print("Colunas após merge:")
# print(df_faturamento_merge.columns)

# %% 10. Agregar Dados para Calcular o Fator de Rateio
faturamento_agg = df_faturamento_merge.groupby(['MODELO', 'CODITEM', 'DESCITEM', 'LINHA'])['Quantidade']\
                                      .sum().reset_index()
total_modelo = faturamento_agg.groupby('MODELO')['Quantidade']\
                              .sum().reset_index().rename(columns={'Quantidade': 'Total_Modelo'})
faturamento_agg = pd.merge(faturamento_agg, total_modelo, on='MODELO')
faturamento_agg['fator_sku'] = faturamento_agg['Quantidade'] / faturamento_agg['Total_Modelo']
# Debug:
# print("Fatores de rateio por SKU:")
# print(faturamento_agg.head())

# %% 11. Verificar Registros sem Correspondência na Tabela de Itens
df_merge_test = pd.merge(df_faturamento_periodo, df_itens,
                         left_on='Cod_produto', right_on='CODITEM',
                         how='left', indicator=True)
df_sem_item = df_merge_test[df_merge_test['_merge'] == 'left_only']
# Debug:
# print("Códigos de produto sem correspondência:")
# print(df_sem_item['Cod_produto'].unique())

# %% 12. Preparar para o Rateio: Unir Forecast com os Fatores de Rateio
df_forecast['modelo_produto'] = df_forecast['modelo_produto'].str.upper().str.strip()
faturamento_agg['MODELO'] = faturamento_agg['MODELO'].str.upper().str.strip()
df_forecast_rateio = pd.merge(df_forecast, faturamento_agg,
                              left_on='modelo_produto', right_on='MODELO', how='left')
df_forecast_rateio['quantidade_rateada'] = df_forecast_rateio['quantidade'] * df_forecast_rateio['fator_sku']
df_forecast_rateio['quantidade_rateada'] = df_forecast_rateio['quantidade_rateada'].fillna(0)
# Debug:
# print("Exemplo do forecast rateado:")
# print(df_forecast_rateio[['id', 'modelo_produto', 'quantidade', 'CODITEM', 'DESCITEM', 'LINHA', 'fator_sku', 'quantidade_rateada']].head())

# %% 13. Montar DataFrame Final para forecast_entries_sku
df_rateio_sku = pd.DataFrame({
    'sku': df_forecast_rateio['CODITEM'],
    'descricao': df_forecast_rateio['DESCITEM'],
    'mes_referencia': df_forecast_rateio['mes_referencia'],
    'empresa': df_forecast_rateio['empresa'],
    'linha': df_forecast_rateio['LINHA'],
    'modelo': df_forecast_rateio['MODELO'],
    'quantidade': df_forecast_rateio['quantidade_rateada'].round().astype(int),
    'codigo_gestor': df_forecast_rateio['cod_gestor'],
    'ip_usuario': df_forecast_rateio['ip_usuario'],
    'data_criacao': datetime.now(),
    'data_edicao': datetime.now()
})
# Debug:
# print("Exemplo do DataFrame final para forecast_entries_sku:")
# print(df_rateio_sku.head())

# %% 14. Empilhar Lançamentos e Inserir na Tabela forecast_system
cols_key = ['sku', 'mes_referencia', 'empresa', 'codigo_gestor']
df_forecast_sku_sel = df_forecast_sku[cols_key + ['descricao','linha','modelo','quantidade','ip_usuario','data_criacao','data_edicao']]
df_empilhado = pd.concat([df_rateio_sku, df_forecast_sku_sel], ignore_index=True)
df_empilhado = df_empilhado.drop_duplicates(subset=cols_key)
df_empilhado_orig = pd.concat([df_rateio_sku, df_forecast_sku_sel], ignore_index=True)
duplicados_df = df_empilhado_orig[df_empilhado_orig.duplicated(subset=cols_key, keep=False)]
# Debug:
# print("Total de registros empilhados antes da remoção de duplicatas:", df_empilhado_orig.shape[0])
# print("Registros duplicados encontrados:")
# print(duplicados_df)
# print("Total de registros após remoção de duplicatas:", df_empilhado.shape[0])

# %% 15. Verificar quais lançamentos já existem na tabela forecast_system
query_forecast_system = "SELECT sku, mes_referencia, empresa, codigo_gestor FROM forecast_system"
df_forecast_system = pd.read_sql(query_forecast_system, engine)
df_empilhado['codigo_gestor'] = df_empilhado['codigo_gestor'].astype(str)
df_forecast_system['codigo_gestor'] = df_forecast_system['codigo_gestor'].astype(str)
df_merge_sys = pd.merge(df_empilhado, df_forecast_system, on=cols_key, how='left', indicator=True)
df_novos = df_merge_sys[df_merge_sys['_merge'] == 'left_only'].drop(columns=['_merge'])
# Debug:
# print("Novos registros a serem inseridos:", df_novos.shape[0])

# %% 16. Inserir os Registros Novos na Tabela forecast_system
df_novos.to_sql('forecast_system', engine, if_exists='append', index=False)
# Debug:
# print("Inserção finalizada!")

# %% 17. Conferência dos Totais (Resumindo)
query_total_entries = "SELECT SUM(quantidade) AS total_entries FROM forecast_entries"
query_total_entries_sku = "SELECT SUM(quantidade) AS total_entries_sku FROM forecast_entries_sku"
query_total_forecast_system = "SELECT SUM(quantidade) AS total_forecast_system FROM forecast_system WHERE codigo_gestor <> '0'"

total_entries = pd.read_sql(query_total_entries, engine).iloc[0]['total_entries']
total_entries_sku = pd.read_sql(query_total_entries_sku, engine).iloc[0]['total_entries_sku']
total_forecast_system = pd.read_sql(query_total_forecast_system, engine).iloc[0]['total_forecast_system']

# Debug:
# print("Total forecast_entries:", total_entries)
# print("Total forecast_entries_sku:", total_entries_sku)
# print("Total forecast_system (gestor <> 0):", total_forecast_system)

# %% 18. Gerar Resumo em JSON e Imprimir
nao_encontrados = len(df_sem_item['Cod_produto'].unique())
resultado = {
    "novos_inseridos": int(df_novos.shape[0]),
    "total_forecast_entries": int(total_entries),
    "total_forecast_entries_sku": int(total_entries_sku),
    "total_forecast_system": int(total_forecast_system),
    "produtos_nao_encontrados": int(nao_encontrados)
}
print(json.dumps(resultado, ensure_ascii=False, separators=(',', ':')))

# %%
