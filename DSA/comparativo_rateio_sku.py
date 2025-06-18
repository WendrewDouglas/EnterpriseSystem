#%% BLOCO 1: Configurações iniciais e supressão de avisos
import sys
import os
import io
import contextlib
import warnings
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
from datetime import datetime
from dateutil.relativedelta import relativedelta
import webbrowser
import json

warnings.simplefilter("ignore", category=pd.errors.SettingWithCopyWarning)
warnings.filterwarnings("ignore", message="DataFrameGroupBy.apply operated on the grouping columns")
print("Bloco 1: Imports e configurações iniciais concluídos.")

#%% BLOCO 2: Verificação do parâmetro Mês de Referência
if len(sys.argv) < 2:
    resultado = {"error": "O parâmetro Mês de Referência é obrigatório."}
    print(json.dumps(resultado, ensure_ascii=False, separators=(',', ':')))
    sys.exit()
mes_ref = "04/2025"
forecast_date = pd.to_datetime(mes_ref, format='%m/%Y')
print("Bloco 2: Mês de Referência definido como:", mes_ref)

#%% BLOCO 3: Configuração dos diretórios e conexão com o banco
current_dir = os.path.dirname(os.path.abspath(__file__))
root_dir = os.path.abspath(os.path.join(current_dir, ".."))
includes_path = os.path.join(root_dir, "includes")
if includes_path not in sys.path:
    sys.path.append(includes_path)
from db_connection import get_db_connection
engine = get_db_connection()
print("Bloco 3: Conexão com o banco estabelecida.")

#%% BLOCO 4: Carregamento dos dados das views/tabelas
query_faturamento = "SELECT * FROM V_FATURAMENTO"
query_forecast_modelo = "SELECT * FROM forecast_entries WHERE finalizado = 1"
query_forecast_sku = "SELECT * FROM forecast_entries_sku WHERE finalizada = 1"
query_itens = "SELECT * FROM V_DEPARA_ITEM"

df_faturamento = pd.read_sql(query_faturamento, engine)
df_forecast_modelo = pd.read_sql(query_forecast_modelo, engine)
df_forecast_sku = pd.read_sql(query_forecast_sku, engine)
df_itens = pd.read_sql(query_itens, engine)
print("Bloco 4: Dados carregados:",
      f"Faturamento {df_faturamento.shape}, Forecast Modelo {df_forecast_modelo.shape},",
      f"Forecast SKU {df_forecast_sku.shape}, Itens {df_itens.shape}")

#%% BLOCO 5: Definição de períodos e preparação dos dados históricos
ultimo_mes_completo = (forecast_date - relativedelta(months=2)).replace(day=1)
periodo1_inicio = (ultimo_mes_completo - relativedelta(months=2)).replace(day=1)
periodo1_fim = ultimo_mes_completo + pd.offsets.MonthEnd(0)
periodo2_inicio = (forecast_date - relativedelta(years=1)).replace(day=1)
periodo2_fim = periodo2_inicio + pd.offsets.MonthEnd(0)

df_faturamento['Data_Faturamento'] = pd.to_datetime(df_faturamento['Data_Faturamento'])
df_hist1 = df_faturamento[
    (df_faturamento['Data_Faturamento'] >= periodo1_inicio) &
    (df_faturamento['Data_Faturamento'] <= periodo1_fim)
]
df_hist2 = df_faturamento[
    (df_faturamento['Data_Faturamento'] >= periodo2_inicio) &
    (df_faturamento['Data_Faturamento'] <= periodo2_fim)
]
df_hist = pd.concat([df_hist1, df_hist2], ignore_index=True)
df_hist = df_hist[df_hist['Quantidade'] >= 0]
print("Bloco 5: Dados históricos processados:",
      f"Histórico final: {df_hist.shape}")

#%% BLOCO 6: Ajuste dos tipos e merge dos históricos com itens
df_hist['Cod_regional'] = df_hist['Cod_regional'].astype(str)
df_hist['Empresa'] = df_hist['Empresa'].astype(str)
df_hist.rename(columns={'Cod_regional': 'codigo_gestor', 'Empresa': 'empresa'}, inplace=True)
df_hist['Cod_produto'] = df_hist['Cod_produto'].astype(str).str.strip()

df_itens['CODITEM'] = df_itens['CODITEM'].astype(str).str.strip()
df_hist_merge = pd.merge(df_hist, df_itens, left_on='Cod_produto', right_on='CODITEM', how='left')
print("Bloco 6: Merge histórico-itens realizado:", f"Resultado: {df_hist_merge.shape}")

#%% BLOCO 7: Cálculo do fator de rateio com base no histórico
hist_agg = df_hist_merge.groupby(
    ['codigo_gestor', 'empresa', 'MODELO', 'CODITEM', 'DESCITEM', 'LINHA']
)['Quantidade'].sum().reset_index()
total_hist = hist_agg.groupby(
    ['codigo_gestor', 'empresa', 'MODELO']
)['Quantidade'].sum().reset_index().rename(columns={'Quantidade': 'Total_Hist'})
hist_agg = pd.merge(hist_agg, total_hist, on=['codigo_gestor', 'empresa', 'MODELO'])
hist_agg['fator_sku'] = hist_agg['Quantidade'] / hist_agg['Total_Hist']
mask = (hist_agg['Total_Hist'] == 0) | (hist_agg['Total_Hist'].isna())
if mask.any():
    sku_count = hist_agg.groupby(
        ['codigo_gestor', 'empresa', 'MODELO']
    )['CODITEM'].transform('count')
    hist_agg.loc[mask, 'fator_sku'] = 1 / sku_count[mask]
print("Bloco 7: Fator de rateio calculado. Exemplo:",
      hist_agg[['CODITEM', 'fator_sku']].head().to_dict(orient='records'))

#%% BLOCO 8: Processamento do Forecast por Modelo
df_forecast_modelo['mes_referencia'] = pd.to_datetime(
    df_forecast_modelo['mes_referencia'], format='%m/%Y'
).dt.strftime('%m/%Y')
df_forecast_modelo.rename(columns={'modelo_produto': 'modelo', 'cod_gestor': 'codigo_gestor'}, inplace=True)
df_forecast_modelo['codigo_gestor'] = df_forecast_modelo['codigo_gestor'].astype(str)
df_forecast_modelo_periodo = df_forecast_modelo[df_forecast_modelo['mes_referencia'] == mes_ref].copy()
total_entries_modelo = df_forecast_modelo_periodo['quantidade'].sum()
print("Bloco 8: Forecast por Modelo para", mes_ref, "carregado:",
      f"Total de entradas: {total_entries_modelo}")

#%% BLOCO 9: Merge do Forecast por Modelo com o histórico para rateio
hist_agg.rename(columns={'MODELO': 'modelo'}, inplace=True)
df_distribuicao = pd.merge(
    hist_agg,
    df_forecast_modelo_periodo,
    on=['codigo_gestor', 'modelo', 'empresa'],
    how='left'
)
mask_modelo_na = df_distribuicao['quantidade'].isna()
if mask_modelo_na.any():
    sku_count_modelo = df_distribuicao.groupby(
        ['codigo_gestor', 'modelo', 'empresa']
    )['CODITEM'].transform('count')
    df_distribuicao.loc[mask_modelo_na, 'fator_sku'] = 1 / sku_count_modelo[mask_modelo_na]
    df_distribuicao.loc[mask_modelo_na, 'quantidade'] = 0
df_distribuicao['quantidade_rateada'] = df_distribuicao['quantidade'] * df_distribuicao['fator_sku']
print("Bloco 9: Distribuição (rateio) calculada para Forecast Modelo.")

#%% BLOCO 10: Agregação do Forecast por Modelo e cálculo do volume
volume_forecast = df_forecast_modelo_periodo.groupby(
    ['empresa', 'codigo_gestor', 'modelo']
)['quantidade'].sum().reset_index().rename(columns={'quantidade': 'qtd_forecast'})
final_df_modelo = pd.merge(
    df_distribuicao,
    volume_forecast,
    on=['empresa', 'codigo_gestor', 'modelo'],
    how='left'
)
final_df_modelo['qtd_forecast'] = final_df_modelo['qtd_forecast'].fillna(0)
final_df_modelo.rename(columns={'CODITEM': 'codigo_produto', 'Quantidade': 'qtd_historico'}, inplace=True)
final_df_modelo = final_df_modelo[[ 
    'empresa', 'codigo_gestor', 'modelo', 'codigo_produto', 
    'qtd_historico', 'fator_sku', 'qtd_forecast', 'quantidade_rateada'
]]
final_df_modelo['origem'] = 'modelo'
print("Bloco 10: Tabela Final Forecast Modelo gerada. Registros:", final_df_modelo.shape[0])

#%% BLOCO 11: Processamento dos grupos faltantes (missing rateio)
grupos_forecast = df_forecast_modelo_periodo[['empresa', 'codigo_gestor', 'modelo']].drop_duplicates()
grupos_hist = hist_agg[['empresa', 'codigo_gestor', 'modelo']].drop_duplicates()
grupos_merge = pd.merge(
    grupos_forecast, grupos_hist,
    on=['empresa', 'codigo_gestor', 'modelo'],
    how='left',
    indicator=True
)
grupos_missing = grupos_merge[grupos_merge['_merge'] == 'left_only']

lista_missing_rateio = []
for _, grupo in grupos_missing.iterrows():
    empresa, codigo_gestor, modelo = grupo['empresa'], grupo['codigo_gestor'], grupo['modelo']
    forecast_qtd = df_forecast_modelo_periodo[
        (df_forecast_modelo_periodo['empresa'] == empresa) &
        (df_forecast_modelo_periodo['codigo_gestor'] == codigo_gestor) &
        (df_forecast_modelo_periodo['modelo'] == modelo)
    ]['quantidade'].sum()
    df_itens['modelo'] = df_itens['MODELO'].str.upper().str.strip()
    modelo_padronizado = modelo.upper().strip()
    skus_modelo = df_itens[df_itens['modelo'] == modelo_padronizado]
    skus_modelo = skus_modelo[~skus_modelo['DESCITEM'].str.contains('EXP', case=False, na=False)]
    skus_modelo = skus_modelo[~skus_modelo['DESCITEM'].str.startswith('CJ', na=False)]
    if skus_modelo.empty:
        continue
    n_skus = skus_modelo.shape[0]
    fator = 1 / n_skus
    for _, sku_row in skus_modelo.iterrows():
        nova_linha = {
            'empresa': empresa,
            'codigo_gestor': codigo_gestor,
            'modelo': modelo,
            'codigo_produto': sku_row['CODITEM'],
            'qtd_historico': 0,
            'fator_sku': fator,
            'qtd_forecast': forecast_qtd,
            'quantidade_rateada': forecast_qtd * fator,
            'origem': 'modelo'
        }
        lista_missing_rateio.append(nova_linha)
df_missing_rateio = pd.DataFrame(lista_missing_rateio)
if not df_missing_rateio.empty:
    final_df_modelo = pd.concat([final_df_modelo, df_missing_rateio], ignore_index=True)
print("Bloco 11: Processamento dos grupos faltantes concluído.",
      f"Registros adicionais: {df_missing_rateio.shape[0]}")

#%% BLOCO 12: Ajuste final de arredondamento por grupo
def ajustar_grupo_modelo(grp):
    forecast_original = grp['qtd_forecast'].iloc[0]
    distributed_sum = grp['quantidade_rateada'].sum()
    diff = distributed_sum - forecast_original
    if abs(diff) > 0:
        grp.iloc[0, grp.columns.get_loc('quantidade_rateada')] -= diff
    return grp

final_df_modelo = final_df_modelo.groupby(
    ['empresa', 'codigo_gestor', 'modelo'], group_keys=False
).apply(ajustar_grupo_modelo)
print("Bloco 12: Ajuste de arredondamento realizado.")

#%% BLOCO 13: Processamento do Forecast por SKU
df_forecast_sku_periodo = df_forecast_sku[df_forecast_sku['mes_referencia'] == mes_ref].copy()
df_forecast_sku_periodo.rename(columns={'sku': 'codigo_produto'}, inplace=True)
df_forecast_sku_periodo['codigo_produto'] = df_forecast_sku_periodo['codigo_produto'].astype(str).str.strip()
df_forecast_sku_periodo = pd.merge(
    df_forecast_sku_periodo, 
    df_itens[['CODITEM', 'DESCITEM', 'LINHA', 'MODELO']], 
    left_on='codigo_produto', right_on='CODITEM', how='left'
)
df_forecast_sku_periodo['modelo'] = df_forecast_sku_periodo['MODELO']
df_forecast_sku_periodo['empresa'] = df_forecast_sku_periodo['empresa'].astype(str)
df_forecast_sku_periodo['codigo_gestor'] = df_forecast_sku_periodo['codigo_gestor'].astype(str)
forecast_sku_final = df_forecast_sku_periodo[[ 
    'codigo_produto', 'quantidade', 'empresa', 'codigo_gestor', 'modelo', 'LINHA', 'DESCITEM'
]].copy()
forecast_sku_final.rename(columns={'quantidade': 'qtd_forecast'}, inplace=True)
forecast_sku_final['qtd_historico'] = 0
forecast_sku_final['fator_sku'] = 1
forecast_sku_final['quantidade_rateada'] = forecast_sku_final['qtd_forecast']
forecast_sku_final['origem'] = 'sku'
print("Bloco 13: Forecast por SKU processado.", f"Registros: {forecast_sku_final.shape[0]}")

#%% BLOCO 14: Formatação do Forecast por SKU para padrão final
forecast_sku_formatted = forecast_sku_final.copy()
forecast_sku_formatted = forecast_sku_formatted.assign(
    sku = forecast_sku_formatted['codigo_produto'],
    descricao = forecast_sku_formatted['DESCITEM'],
    mes_referencia = mes_ref,
    ip_usuario = "",
    data_criacao = datetime.now(),
    data_edicao = datetime.now()
)
forecast_sku_formatted = forecast_sku_formatted[[ 
    'mes_referencia', 'codigo_gestor', 'empresa', 'modelo', 'sku', 
    'descricao', 'LINHA', 'qtd_forecast', 'ip_usuario', 'data_criacao', 'data_edicao'
]]
forecast_sku_formatted.rename(columns={'qtd_forecast': 'quantidade'}, inplace=True)
forecast_sku_formatted['quantidade'] = forecast_sku_formatted['quantidade'].round().astype(int)
print("Bloco 14: Forecast SKU formatado para padrão final.")

#%% BLOCO 15: Formatação do Forecast por Modelo para padrão final
df_itens_unique = df_itens.drop_duplicates(subset=['CODITEM'])
df_itens_unique['CODITEM'] = df_itens_unique['CODITEM'].astype(str).str.strip()
final_df_formatted = pd.merge(
    final_df_modelo, 
    df_itens_unique[['CODITEM', 'DESCITEM', 'LINHA']], 
    left_on='codigo_produto', right_on='CODITEM', how='left', validate='m:1'
)
final_df_formatted = final_df_formatted.assign(
    sku = final_df_formatted['codigo_produto'],
    descricao = final_df_formatted['DESCITEM'],
    mes_referencia = mes_ref,
    ip_usuario = "",
    data_criacao = datetime.now(),
    data_edicao = datetime.now()
)
final_df_formatted = final_df_formatted[[ 
    'mes_referencia', 'codigo_gestor', 'empresa', 'modelo', 'sku', 
    'descricao', 'LINHA', 'quantidade_rateada', 'ip_usuario', 'data_criacao', 'data_edicao'
]]
final_df_formatted.rename(columns={'quantidade_rateada': 'quantidade'}, inplace=True)
final_df_formatted['quantidade'] = final_df_formatted['quantidade'].round().astype(int)
final_df_formatted = final_df_formatted.drop_duplicates(subset=['mes_referencia', 'empresa', 'codigo_gestor', 'modelo', 'sku'])
print("Bloco 15: Forecast Modelo formatado para padrão final. Registros:", final_df_formatted.shape[0])

#%% BLOCO 16: Empilhamento Final: Forecast por Modelo + Forecast por SKU
final_table = pd.concat([final_df_formatted, forecast_sku_formatted], ignore_index=True)
print("Bloco 16: Tabela final empilhada. Total registros:", final_table.shape[0])

#%% BLOCO 17: Cálculo dos Totais Originais e Rateados
df_total_entries = pd.read_sql(
    f"SELECT SUM(quantidade) AS total_entries FROM forecast_entries WHERE mes_referencia = '{mes_ref}'", engine
)
total_entries_modelo = df_total_entries.iloc[0]['total_entries'] if df_total_entries.iloc[0]['total_entries'] is not None else 0

df_total_entries_sku = pd.read_sql(
    f"SELECT SUM(quantidade) AS total_entries_sku FROM forecast_entries_sku WHERE mes_referencia = '{mes_ref}'", engine
)
total_entries_sku = df_total_entries_sku.iloc[0]['total_entries_sku'] if df_total_entries_sku.iloc[0]['total_entries_sku'] is not None else 0

total_forecast_original = total_entries_modelo + total_entries_sku
total_rateado = final_table['quantidade'].sum()
print("Bloco 17: Totais calculados:",
      f"Forecast Original (Modelo + SKU): {total_forecast_original}, Total Rateado: {total_rateado}")

#%% BLOCO 18: Preparação dos dados para inserção final
final_table_to_insert = final_table.copy()
final_table_to_insert = final_table_to_insert.rename(columns={
    'sku': 'sku',
    'descricao': 'descricao',
    'mes_referencia': 'mes_referencia',
    'empresa': 'empresa',
    'LINHA': 'linha',
    'modelo': 'modelo',
    'quantidade': 'quantidade',
    'codigo_gestor': 'codigo_gestor',
    'ip_usuario': 'ip_usuario',
    'data_criacao': 'data_criacao',
    'data_edicao': 'data_edicao'
})
final_table_to_insert = final_table_to_insert[[ 
    'sku', 'descricao', 'mes_referencia', 'empresa', 'linha', 'modelo',
    'quantidade', 'codigo_gestor', 'ip_usuario', 'data_criacao', 'data_edicao'
]]
final_table_to_insert = final_table_to_insert[final_table_to_insert['quantidade'] != 0]
final_table_to_insert['data_criacao'] = pd.to_datetime(final_table_to_insert['data_criacao'])
final_table_to_insert['data_edicao'] = pd.to_datetime(final_table_to_insert['data_edicao'])
print("Bloco 18: Dados preparados para inserção. Registros a inserir:", final_table_to_insert.shape[0])

#%% BLOCO 19: Identificação de registros novos e inserção na tabela final
query_existentes = f"""
SELECT mes_referencia, empresa, codigo_gestor, sku, modelo, quantidade
FROM forecast_system
WHERE mes_referencia = '{mes_ref}'
"""
df_existentes = pd.read_sql(query_existentes, engine)
chaves = ['mes_referencia', 'empresa', 'codigo_gestor', 'sku', 'modelo']
df_novos = final_table_to_insert.merge(df_existentes[chaves], on=chaves, how='left', indicator=True)
df_novos = df_novos[df_novos['_merge'] == 'left_only'].drop(columns=['_merge'])

total_quant_final = int(final_table_to_insert['quantidade'].sum())
total_quant_existente = int(df_existentes['quantidade'].sum()) if not df_existentes.empty else 0
total_quant_novos = int(df_novos['quantidade'].sum())
diff_arredondamento = int(total_rateado - total_forecast_original)
print("Bloco 19: Comparação final de quantidades:",
      f"Total final: {total_quant_final}, Existente: {total_quant_existente},",
      f"Novos: {total_quant_novos}, Diferença de arredondamento: {diff_arredondamento}")

if total_quant_novos > 0:
    df_novos.to_sql('forecast_system', con=engine, if_exists='append', index=False)
    print("Bloco 19: Novos registros inseridos na tabela forecast_system.")
else:
    print("Bloco 19: Nenhum registro novo a ser inserido.")

#%% BLOCO 20: Impressão do resultado final (JSON)
resultado = {
    "Mês atualizado": mes_ref,
    "Quantidade de itens": total_quant_final,
    "Quantidade existente": total_quant_existente,
    "Quantidade atualizada": total_quant_novos,
    "Quantidade desviada por arredondamento": diff_arredondamento
}
print(json.dumps(resultado, ensure_ascii=False, separators=(',', ':')))

#%% BLOCO 21: Comparativo de Quantidades entre Forecast e Rateado

# Consulta e agregação dos valores forecastados (tabela forecast_entries)
query_forecast_modelo_total = f"""
SELECT empresa, cod_gestor AS codigo_gestor, modelo_produto AS modelo, SUM(quantidade) AS total_forecast
FROM forecast_entries
WHERE mes_referencia = '{mes_ref}'
GROUP BY empresa, cod_gestor, modelo_produto
"""
df_forecast_total = pd.read_sql(query_forecast_modelo_total, engine)
df_forecast_total['codigo_gestor'] = df_forecast_total['codigo_gestor'].astype(str)
df_forecast_total['modelo'] = df_forecast_total['modelo'].str.strip()

# Consulta e agregação dos valores rateados (tabela forecast_system)
query_rateado_total = f"""
SELECT empresa, codigo_gestor, modelo, SUM(quantidade) AS total_rateado
FROM forecast_system
WHERE mes_referencia = '{mes_ref}'
GROUP BY empresa, codigo_gestor, modelo
"""
df_rateado_total = pd.read_sql(query_rateado_total, engine)
df_rateado_total['codigo_gestor'] = df_rateado_total['codigo_gestor'].astype(str)
df_rateado_total['modelo'] = df_rateado_total['modelo'].str.strip()

# Merge dos DataFrames para comparar os totais
df_comparativo = pd.merge(df_forecast_total, df_rateado_total, on=['empresa', 'codigo_gestor', 'modelo'], how='outer')
df_comparativo.fillna(0, inplace=True)

# Cálculo da diferença absoluta e percentual
df_comparativo['diferenca'] = df_comparativo['total_rateado'] - df_comparativo['total_forecast']
df_comparativo['percentual_diferenca'] = (df_comparativo['diferenca'] / df_comparativo['total_forecast']).replace([float('inf'), -float('inf')], 0) * 100

print("Bloco 21: Comparativo de Quantidades entre Forecast e Rateado:")
print(df_comparativo)


#%% BLOCO 22: Registros com diferenças maiores que 2 unidades
df_diferenca_grande = df_comparativo[df_comparativo['diferenca'].abs() > 2]
print("Bloco 22: Registros com diferenças maiores que 2 unidades:")
print(df_diferenca_grande)


# %%
