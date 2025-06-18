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
import webbrowser  # Para abrir HTML no navegador

warnings.simplefilter(action='ignore', category=pd.errors.SettingWithCopyWarning)

current_dir = os.path.dirname(os.path.abspath(__file__))
root_dir = os.path.abspath(os.path.join(current_dir, ".."))
includes_path = os.path.join(root_dir, "includes")
if includes_path not in sys.path:
    sys.path.append(includes_path)

from db_connection import get_db_connection
engine = get_db_connection()
print("Conexão com o banco estabelecida.")

# %% 2. Carregar Dados: Histórico (V_FATURAMENTO), Forecast por Modelo e Itens
query_faturamento = "SELECT * FROM V_FATURAMENTO"
query_forecast_modelo = "SELECT * FROM forecast_entries"
query_itens = "SELECT * FROM V_DEPARA_ITEM"

df_faturamento = pd.read_sql(query_faturamento, engine)
df_forecast_modelo = pd.read_sql(query_forecast_modelo, engine)
df_itens = pd.read_sql(query_itens, engine)

print("Dados carregados:")
print("Faturamento:", df_faturamento.shape)
print("Forecast Modelo:", df_forecast_modelo.shape)
print("Itens:", df_itens.shape)

# %% 3. Converter Datas nos Dados Históricos
df_faturamento['Data_Faturamento'] = pd.to_datetime(df_faturamento['Data_Faturamento'])
print("Data_Faturamento convertida para datetime.")

# %% 4. Filtrar Histórico para os Períodos Relevantes
# Utilizaremos os últimos 3 meses completos e o mesmo mês do ano anterior,
# considerando o mês seguinte ao atual como referência para o histórico.
data_atual = pd.Timestamp.today()
ultimo_mes_completo = data_atual.replace(day=1) - pd.Timedelta(days=1)
print("Último mês completo:", ultimo_mes_completo.date())

# Período 1: últimos 3 meses completos
periodo1_inicio = ultimo_mes_completo.replace(day=1) - relativedelta(months=2)
periodo1_fim = ultimo_mes_completo
print("Período 1 (últimos 3 meses completos):", periodo1_inicio.date(), "a", periodo1_fim.date())

# Período 2: mesmo mês do ano anterior, baseado no mês seguinte ao atual
mes_seguinte = (data_atual + relativedelta(months=1)).replace(day=1)
mes_anterior = mes_seguinte - relativedelta(years=1)
periodo2_inicio = mes_anterior
periodo2_fim = (mes_anterior + relativedelta(months=1)) - pd.Timedelta(days=1)
print("Período 2 (mesmo mês do ano anterior, baseado no mês seguinte ao atual):", periodo2_inicio.date(), "a", periodo2_fim.date())

df_hist1 = df_faturamento[(df_faturamento['Data_Faturamento'] >= periodo1_inicio) & (df_faturamento['Data_Faturamento'] <= periodo1_fim)]
df_hist2 = df_faturamento[(df_faturamento['Data_Faturamento'] >= periodo2_inicio) & (df_faturamento['Data_Faturamento'] <= periodo2_fim)]
df_hist = pd.concat([df_hist1, df_hist2], ignore_index=True)
print("Total registros históricos filtrados:", df_hist.shape)

# Adicionar a coluna mes_referencia ao histórico (apenas para verificação)
df_hist['mes_referencia'] = df_hist['Data_Faturamento'].dt.strftime('%m/%Y')
print("Exemplo de mes_referencia no histórico:", df_hist['mes_referencia'].unique())

# %% 5. Remover Registros com Quantidades Negativas no Histórico
df_hist = df_hist[df_hist['Quantidade'] >= 0]
print("Registros históricos após remover negativos:", df_hist.shape)

# %% 6. Converter Chaves para String no Histórico e Unificar Nome do Gestor
df_hist['Cod_regional'] = df_hist['Cod_regional'].astype(str)
df_hist['Empresa'] = df_hist['Empresa'].astype(str)
df_hist.rename(columns={'Cod_regional': 'codigo_gestor', 'Empresa': 'empresa'}, inplace=True)
df_hist['Cod_produto'] = df_hist['Cod_produto'].astype(str)
print("Chaves do histórico convertidas para string.")

# %% 7. Unir Histórico com Itens para Trazer Detalhes dos SKUs
df_itens['CODITEM'] = df_itens['CODITEM'].astype(str)
df_hist_merge = pd.merge(df_hist, df_itens, left_on='Cod_produto', right_on='CODITEM', how='left')
print("Merge histórico + itens (head):")
print(df_hist_merge.head())
print("Colunas após merge:", df_hist_merge.columns.tolist())

# %% 8. Agregar Histórico para Calcular o Fator de Rateio
# Agrupar por (codigo_gestor, empresa, modelo, CODITEM, DESCITEM, LINHA)
# (Não incluímos mes_referencia, pois o forecast já está filtrado para um determinado mês)
hist_agg = df_hist_merge.groupby(['codigo_gestor', 'empresa', 'MODELO', 'CODITEM', 'DESCITEM', 'LINHA'])['Quantidade']\
                        .sum().reset_index()
# Calcular o total histórico para cada grupo (codigo_gestor, empresa, MODELO)
total_hist = hist_agg.groupby(['codigo_gestor', 'empresa', 'MODELO'])['Quantidade']\
                     .sum().reset_index().rename(columns={'Quantidade': 'Total_Hist'})
hist_agg = pd.merge(hist_agg, total_hist, on=['codigo_gestor', 'empresa', 'MODELO'])
hist_agg['fator_sku'] = hist_agg['Quantidade'] / hist_agg['Total_Hist']
print("Histórico agregado para fator de rateio:")
print(hist_agg.head())

# Verificar se a soma dos fatores por grupo (empresa, codigo_gestor, MODELO) é 1
soma_fatores = hist_agg.groupby(['empresa', 'codigo_gestor', 'MODELO'])['fator_sku'].sum().reset_index()
print("Soma dos fatores por grupo (deveria ser 1):")
print(soma_fatores.head())

# Para grupos sem histórico (Total_Hist == 0 ou NaN), definir fator igual entre os SKUs
mask = (hist_agg['Total_Hist'] == 0) | (hist_agg['Total_Hist'].isna())
if mask.any():
    sku_count = hist_agg.groupby(['codigo_gestor', 'empresa', 'MODELO'])['CODITEM'].transform('count')
    hist_agg.loc[mask, 'fator_sku'] = 1 / sku_count[mask]
print("Fatores SKU calculados (histórico):")
print(hist_agg[['codigo_gestor','empresa','MODELO','CODITEM','fator_sku']].head(10))

# %% 9. Carregar Forecast por Modelo (Volume a Distribuir)
# Converter mes_referencia para string no formato "MM/YYYY"
df_forecast_modelo['mes_referencia'] = pd.to_datetime(df_forecast_modelo['mes_referencia'], format='%m/%Y').dt.strftime('%m/%Y')
# Renomear se necessário: supondo que a coluna de modelo originalmente se chame "modelo_produto" e de gestor "cod_gestor"
df_forecast_modelo.rename(columns={'modelo_produto': 'modelo', 'cod_gestor': 'codigo_gestor'}, inplace=True)
df_forecast_modelo['codigo_gestor'] = df_forecast_modelo['codigo_gestor'].astype(str)
print("Forecast por modelo:")
print(df_forecast_modelo[['codigo_gestor','modelo','mes_referencia','empresa','quantidade']].head())
print("Valores únicos de mes_referencia no forecast:", df_forecast_modelo['mes_referencia'].unique())

# %% 10. Filtrar Forecast por Modelo para o Período de Interesse
mes_forecast_escolhido = "04/2025"  # valor vindo do sistema
df_forecast_modelo_periodo = df_forecast_modelo[df_forecast_modelo['mes_referencia'] == mes_forecast_escolhido].copy()
print("Forecast modelo para o período", mes_forecast_escolhido, ":", df_forecast_modelo_periodo.shape)
total_forecast = df_forecast_modelo_periodo['quantidade'].sum()
print("Total forecast para o período", mes_forecast_escolhido, "é:", total_forecast)

# %% 11. Unir o Forecast por Modelo com o Histórico para Obter o Fator de Rateio
# No histórico, renomeamos "MODELO" para "modelo" para unificar
hist_agg.rename(columns={'MODELO': 'modelo'}, inplace=True)
df_distribuicao = pd.merge(hist_agg, df_forecast_modelo_periodo,
                           on=['codigo_gestor', 'modelo', 'empresa'], how='left')
# Se não houver forecast para um grupo (quantidade é NaN), atribuir distribuição igualitária entre os SKUs
mask_modelo_na = df_distribuicao['quantidade'].isna()
if mask_modelo_na.any():
    sku_count_modelo = df_distribuicao.groupby(['codigo_gestor', 'modelo', 'empresa'])['CODITEM'].transform('count')
    df_distribuicao.loc[mask_modelo_na, 'fator_sku'] = 1 / sku_count_modelo[mask_modelo_na]
    df_distribuicao.loc[mask_modelo_na, 'quantidade'] = 0
df_distribuicao['quantidade_rateada'] = df_distribuicao['quantidade'] * df_distribuicao['fator_sku']

# Exibir o DataFrame de distribuição completo no navegador (todas as linhas)
html_output = df_distribuicao.to_html()
with open("debug_output.html", "w", encoding="utf-8") as f:
    f.write(html_output)
webbrowser.open("debug_output.html")
print("HTML da distribuição do forecast gerado e aberto no navegador.")

# %% 12. Gerar Tabela Final com Volume Forecast e Distribuição
# Agrupar o forecast por modelo para obter o volume forecastado total por grupo (empresa, codigo_gestor, modelo)
volume_forecast = df_forecast_modelo_periodo.groupby(['empresa', 'codigo_gestor', 'modelo'])['quantidade']\
                                           .sum().reset_index()
volume_forecast.rename(columns={'quantidade': 'qtd_forecast'}, inplace=True)
print("Volume forecast agregado por grupo:")
print(volume_forecast.head())

# Fazer merge do volume forecast com a tabela de distribuição (df_distribuicao)
final_df = pd.merge(df_distribuicao, volume_forecast,
                    on=['empresa', 'codigo_gestor', 'modelo'], how='left')
final_df['qtd_forecast'] = final_df['qtd_forecast'].fillna(0)
# Renomear colunas: "CODITEM" -> "codigo_produto", "Quantidade" -> "qtd_historico"
final_df.rename(columns={'CODITEM': 'codigo_produto', 'Quantidade': 'qtd_historico'}, inplace=True)
# Selecionar as colunas finais desejadas
final_df = final_df[['empresa', 'codigo_gestor', 'modelo', 'codigo_produto', 'qtd_historico', 'fator_sku', 'qtd_forecast', 'quantidade_rateada']]
print("Tabela final com volume forecast e distribuição:")
print(final_df)

# Exibir a tabela final completa no navegador
html_final = final_df.to_html()
with open("final_distribution.html", "w", encoding="utf-8") as f:
    f.write(html_final)
webbrowser.open("final_distribution.html")

# %% 13. Calcular e Adicionar Totais na Tabela Final
totais = final_df[['qtd_historico', 'qtd_forecast', 'quantidade_rateada']].sum()
print("Totais da tabela final:")
print(totais)

totais_df = pd.DataFrame([{
    'empresa': 'TOTAL',
    'codigo_gestor': '',
    'modelo': '',
    'codigo_produto': '',
    'qtd_historico': totais['qtd_historico'],
    'fator_sku': '',
    'qtd_forecast': totais['qtd_forecast'],
    'quantidade_rateada': totais['quantidade_rateada']
}])
final_df_com_totais = pd.concat([final_df, totais_df], ignore_index=True)
html_final_totais = final_df_com_totais.to_html()
with open("final_distribution_with_totals.html", "w", encoding="utf-8") as f:
    f.write(html_final_totais)
webbrowser.open("final_distribution_with_totals.html")

# %% 14. Verificar Grupos do Forecast que Não Foram Encontrados no Histórico
# Extrair grupos únicos do forecast (do período filtrado)
grupos_forecast = df_forecast_modelo_periodo[['empresa', 'codigo_gestor', 'modelo']].drop_duplicates()
print("Grupos únicos no forecast:")
print(grupos_forecast)

# Extrair grupos únicos do histórico agregado (hist_agg) usando as chaves padronizadas
grupos_hist = hist_agg[['empresa', 'codigo_gestor', 'modelo']].drop_duplicates()
print("Grupos únicos no histórico:")
print(grupos_hist)

# Merge com indicador para identificar grupos do forecast não encontrados no histórico
grupos_merge = pd.merge(grupos_forecast, grupos_hist, on=['empresa', 'codigo_gestor', 'modelo'], how='left', indicator=True)
grupos_missing = grupos_merge[grupos_merge['_merge'] == 'left_only']
print("Grupos do forecast sem correspondência no histórico:")
print(grupos_missing)

# %% 15. Processar Grupos Missing para Distribuição Igualitária
# Para cada grupo do forecast sem correspondência no histórico, vamos buscar os SKUs da tabela de itens
# e atribuir um fator igual (1 / número de SKUs), repetindo o volume forecast para cada SKU.
print("Processando grupos do forecast sem histórico para distribuição igualitária...")

lista_missing_rateio = []
for idx, grupo in grupos_missing.iterrows():
    empresa = grupo['empresa']
    codigo_gestor = grupo['codigo_gestor']
    modelo = grupo['modelo']
    # Obter o volume forecast para o grupo
    forecast_qtd = df_forecast_modelo_periodo[
        (df_forecast_modelo_periodo['empresa'] == empresa) &
        (df_forecast_modelo_periodo['codigo_gestor'] == codigo_gestor) &
        (df_forecast_modelo_periodo['modelo'] == modelo)
    ]['quantidade'].sum()
    
    # Obter os SKUs para o modelo a partir da tabela de itens (df_itens)
    # Padronizar a coluna modelo para comparação
    df_itens['modelo'] = df_itens['MODELO'].str.upper().str.strip()
    modelo_padronizado = modelo.upper().strip()
    skus_modelo = df_itens[df_itens['modelo'] == modelo_padronizado]
    
    if skus_modelo.empty:
        print(f"Grupo {empresa}-{codigo_gestor}-{modelo} não possui SKUs na tabela de itens.")
        continue
        
    n_skus = skus_modelo.shape[0]
    fator = 1 / n_skus
    
    for idx2, sku_row in skus_modelo.iterrows():
        nova_linha = {
            'empresa': empresa,
            'codigo_gestor': codigo_gestor,
            'modelo': modelo,
            'codigo_produto': sku_row['CODITEM'],
            'qtd_historico': 0,
            'fator_sku': fator,
            'qtd_forecast': forecast_qtd,
            'quantidade_rateada': forecast_qtd * fator
        }
        lista_missing_rateio.append(nova_linha)

df_missing_rateio = pd.DataFrame(lista_missing_rateio)
print("Tabela de rateio para grupos sem histórico:")
print(df_missing_rateio)

# Contar quantos grupos tiveram distribuição igualitária (ou seja, não foram encontrados no histórico)
num_grupos_missing = grupos_missing.shape[0]
print("Número de grupos do forecast sem histórico:", num_grupos_missing)

# Concatenar com a tabela final
final_df = pd.concat([final_df, df_missing_rateio], ignore_index=True)
print("Tabela final atualizada com grupos sem histórico:")
print(final_df)

# Exibir a tabela final completa no navegador
html_final = final_df.to_html()
with open("final_distribution_full.html", "w", encoding="utf-8") as f:
    f.write(html_final)
webbrowser.open("final_distribution_full.html")

# %%
# %% 16. Calcular e Adicionar Totais na Tabela Final
totais = final_df[['qtd_historico', 'qtd_forecast', 'quantidade_rateada']].sum()
print("Totais da tabela final:")
print(totais)

totais_df = pd.DataFrame([{
    'empresa': 'TOTAL',
    'codigo_gestor': '',
    'modelo': '',
    'codigo_produto': '',
    'qtd_historico': totais['qtd_historico'],
    'fator_sku': '',
    'qtd_forecast': totais['qtd_forecast'],
    'quantidade_rateada': totais['quantidade_rateada']
}])

final_df_com_totais = pd.concat([final_df, totais_df], ignore_index=True)

html_final_totais = final_df_com_totais.to_html()
with open("final_distribution_with_totals.html", "w", encoding="utf-8") as f:
    f.write(html_final_totais)
webbrowser.open("final_distribution_with_totals.html")
print("Tabela final com totais gerada e aberta no navegador.")

# %% 17. Formatar Tabela Final no Padrão forecast_entries_sku

# Realizar merge para trazer as colunas 'DESCITEM' (descrição) e 'LINHA' da tabela de itens
final_df_formatted = pd.merge(final_df, 
                              df_itens[['CODITEM', 'DESCITEM', 'LINHA']], 
                              left_on='codigo_produto', right_on='CODITEM', 
                              how='left')

# Criar as colunas necessárias conforme a estrutura de forecast_entries_sku:
# Estrutura: id, sku, descricao, mes_referencia, empresa, linha, modelo, quantidade, codigo_gestor, ip_usuario, data_criacao, data_edicao.
# O campo "id" geralmente é autoincrementado, então não o incluímos.
final_df_formatted = final_df_formatted.assign(
    sku = final_df_formatted['codigo_produto'],
    descricao = final_df_formatted['DESCITEM'],
    mes_referencia = mes_forecast_escolhido,  # Valor do forecast escolhido
    ip_usuario = "",                         # Valor padrão (vazio)
    data_criacao = datetime.now(),
    data_edicao = datetime.now()
)

# Selecionar as colunas na ordem desejada
final_df_formatted = final_df_formatted[['sku', 'descricao', 'mes_referencia', 'empresa', 'LINHA', 'modelo', 
                                           'quantidade_rateada', 'codigo_gestor', 'ip_usuario', 'data_criacao', 'data_edicao']]

# Renomear a coluna 'quantidade_rateada' para 'quantidade'
final_df_formatted.rename(columns={'quantidade_rateada': 'quantidade'}, inplace=True)

# Converter a coluna 'quantidade' para número inteiro (aplicando arredondamento)
final_df_formatted['quantidade'] = final_df_formatted['quantidade'].round().astype(int)

print("Tabela final formatada (forecast_entries_sku):")
print(final_df_formatted)

# Exibir a tabela final formatada completa no navegador
html_final_formatted = final_df_formatted.to_html()
with open("final_distribution_formatted.html", "w", encoding="utf-8") as f:
    f.write(html_final_formatted)
webbrowser.open("final_distribution_formatted.html")


# %% 18. Empilhar Dados de forecast_entries_sku com a Tabela Final Formatada

# Selecionar as colunas relevantes do DataFrame forecast_entries_sku, que já deve ter a estrutura similar à forecast_entries_sku.
# Supondo que df_forecast_sku já foi carregado no Bloco 2.
cols_final = ['sku', 'descricao', 'mes_referencia', 'empresa', 'linha', 'modelo', 'quantidade', 'codigo_gestor', 'ip_usuario', 'data_criacao', 'data_edicao']
df_forecast_sku_sel = df_forecast_sku[cols_final].copy()

# Converter a coluna 'quantidade' para inteiro, se necessário (aqui, assume-se que já estão no formato desejado)
df_forecast_sku_sel['quantidade'] = df_forecast_sku_sel['quantidade'].round().astype(int)

# Empilhar (concatenar) a tabela final formatada (final_df_formatted, construída no bloco 17) com os dados de forecast_entries_sku
final_table = pd.concat([final_df_formatted, df_forecast_sku_sel], ignore_index=True)

print("Tabela final após empilhamento com dados de forecast_entries_sku:")
print(final_table)

# Exibir a tabela final empilhada completa no navegador
html_final_empilhado = final_table.to_html()
with open("final_distribution_empilhada.html", "w", encoding="utf-8") as f:
    f.write(html_final_empilhado)
webbrowser.open("final_distribution_empilhada.html")




# %% Comparação dos Totais

# Definir o mês de referência para a comparação
mes_ref = "04/2025"

# 1. Somar a coluna 'quantidade' da tabela final (final_df_formatted)
total_final = final_table['quantidade'].sum()

# 2. Consultar o total forecast na tabela forecast_entries para o mês
query_total_entries = f"SELECT SUM(quantidade) AS total_entries FROM forecast_entries WHERE mes_referencia = '{mes_ref}'"
df_total_entries = pd.read_sql(query_total_entries, engine)
total_entries = df_total_entries.iloc[0]['total_entries'] if df_total_entries.iloc[0]['total_entries'] is not None else 0

# 3. Consultar o total forecast na tabela forecast_entries_sku para o mesmo mês
query_total_entries_sku = f"SELECT SUM(quantidade) AS total_entries_sku FROM forecast_entries_sku WHERE mes_referencia = '{mes_ref}'"
df_total_entries_sku = pd.read_sql(query_total_entries_sku, engine)
total_entries_sku = df_total_entries_sku.iloc[0]['total_entries_sku'] if df_total_entries_sku.iloc[0]['total_entries_sku'] is not None else 0

# 4. Somar os totais do forecast original (forecast_entries + forecast_entries_sku)
total_forecast_original = total_entries + total_entries_sku

# Imprimir os resultados
print("Soma da coluna 'quantidade' da tabela final:", total_final)
print("Soma das quantidades em forecast_entries para", mes_ref, ":", total_entries)
print("Soma das quantidades em forecast_entries_sku para", mes_ref, ":", total_entries_sku)
print("Soma total forecast_entries + forecast_entries_sku:", total_forecast_original)

# %%
