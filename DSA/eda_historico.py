# %% Importar bibliotecas
import sys
import os
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
from datetime import datetime
from dateutil.relativedelta import relativedelta

# %% Criar conexão com o banco
sys.path.append(os.path.abspath("C:/xampp/htdocs/forecast/includes/"))
from db_connection import get_db_connection
engine = get_db_connection()

# %% Ler os registros já existentes na tabela forecast_system (apenas as colunas de chave)
# Critério de unicidade: sku, mes_referencia, empresa, codigo_gestor
query_forecast_system = "SELECT sku, mes_referencia, empresa, codigo_gestor FROM forecast_system"
df_forecast_system = pd.read_sql(query_forecast_system, engine)

# %% Preparar os dados históricos (forecast_pcp)
query_forecast_pcp = "SELECT * FROM forecast_pcp"
df_forecast_pcp = pd.read_sql(query_forecast_pcp, engine)

# %% Filtrar registros até fevereiro/2025
# Converter a coluna mes_referencia para datetime (se já não estiver) e filtrar
df_forecast_pcp['mes_referencia_dt'] = pd.to_datetime(df_forecast_pcp['mes_referencia'])
cutoff = pd.to_datetime("2025-02-01")
df_forecast_pcp = df_forecast_pcp[df_forecast_pcp['mes_referencia_dt'] <= cutoff]

# Converter mes_referencia para string no formato 'MM/YYYY'
df_forecast_pcp['mes_referencia'] = df_forecast_pcp['mes_referencia_dt'].dt.strftime('%m/%Y')
df_forecast_pcp.drop(columns=['mes_referencia_dt'], inplace=True)

# %% Converter 'cod_produto' para string e remover o sufixo ".0"
df_forecast_pcp['cod_produto'] = df_forecast_pcp['cod_produto'].astype(str).replace(r'\.0$', '', regex=True)

# %% Converter 'Empresa' para string sem ".0" e salvar em uma nova coluna 'empresa'
df_forecast_pcp['empresa'] = df_forecast_pcp['Empresa'].astype(str).replace(r'\.0$', '', regex=True)

# %% Converter 'quantidade' para inteiro
df_forecast_pcp['quantidade'] = df_forecast_pcp['quantidade'].astype(float).astype(int)

# %% Carregar os dados de depara_item (necessário para o merge)
query_depara = "SELECT * FROM V_DEPARA_ITEM"
df_itens = pd.read_sql(query_depara, engine)

# Garantir que a chave para merge com depara_item esteja no mesmo formato
df_itens['CODITEM'] = df_itens['CODITEM'].astype(str)

# %% Fazer merge do histórico com a tabela depara_item para obter informações adicionais
df_forecast_pcp_merge = pd.merge(df_forecast_pcp, df_itens, left_on='cod_produto', right_on='CODITEM', how='left')

# %% Montar DataFrame para inserir no histórico na tabela forecast_system
df_forecast_pcp_final = pd.DataFrame({
    'sku': df_forecast_pcp_merge['cod_produto'],              # SKU (ou CODITEM)
    'descricao': df_forecast_pcp_merge['DESCITEM'],             # Descrição do produto
    'mes_referencia': df_forecast_pcp_merge['mes_referencia'],  # Formato 'MM/YYYY'
    'empresa': df_forecast_pcp_merge['empresa'],                # Empresa, já convertida
    'linha': df_forecast_pcp_merge['LINHA'],                    # Linha
    'modelo': df_forecast_pcp_merge['MODELO'],                  # Modelo
    'quantidade': df_forecast_pcp_merge['quantidade'],          # Quantidade histórica
    'codigo_gestor': 0,                                         # Não há informação no histórico
    'ip_usuario': '',                                         # Pode ser vazio
    'data_criacao': datetime.now(),
    'data_edicao': datetime.now()
})

print("Exemplo do DataFrame final para histórico:")
print(df_forecast_pcp_final.head())

# %% Converter a coluna 'codigo_gestor' para string em ambos os DataFrames
df_forecast_pcp_final['codigo_gestor'] = df_forecast_pcp_final['codigo_gestor'].astype(str)
df_forecast_system['codigo_gestor'] = df_forecast_system['codigo_gestor'].astype(str)

# %% Converter a coluna mes_referencia do forecast_system para datetime e depois para string no formato "MM/YYYY"
df_forecast_system['mes_referencia'] = pd.to_datetime(df_forecast_system['mes_referencia'], errors='coerce').dt.strftime('%m/%Y')

# %% Verificar quais lançamentos do histórico já existem na tabela forecast_system
# Critério de unicidade: sku, mes_referencia, empresa, codigo_gestor
cols_key = ['sku', 'mes_referencia', 'empresa', 'codigo_gestor']
df_merge_hist = pd.merge(df_forecast_pcp_final, df_forecast_system, on=cols_key, how='left', indicator=True)
df_hist_novos = df_merge_hist[df_merge_hist['_merge'] == 'left_only'].drop(columns=['_merge'])
print("Novos lançamentos de histórico a serem inseridos:", df_hist_novos.shape[0])

# %% Inserir os registros novos do histórico na tabela forecast_system
df_hist_novos.to_sql('forecast_system', engine, if_exists='append', index=False)
print("Inserção do histórico concluída!")

# %%
