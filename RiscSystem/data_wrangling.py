#%% Instalando os pacotes

!pip install pandas
!pip install numpy
!pip install matplotlib
!pip install seaborn
!pip install xlrd #ajuda a rodar arquivos do excel com o pandas

#%%
import os
import sys
import pandas as pd
import numpy as np
from sqlalchemy import text
import seaborn as sns
import matplotlib.pyplot as plt

#%%
# 📦 Definir caminhos para importar os arquivos de conexão
current_dir = os.path.dirname(os.path.abspath(__file__))
root_dir = os.path.abspath(os.path.join(current_dir, ".."))
includes_path = os.path.join(root_dir, "includes")

if includes_path not in sys.path:
    sys.path.append(includes_path)

# 🚀 Importar os três arquivos de conexão
from db_connection import get_db_connection as conn_operacional
from db_connection_DW import get_db_connection as conn_dw
from db_connection_PWRBI import get_db_connection as conn_pwrbi

# 🔗 Criar conexões
conn_op = conn_operacional()
conn_dw = conn_dw()
conn_pbi = conn_pwrbi()

print("✅ Conexões com os três bancos foram realizadas com sucesso!")
#%% 📄 Função para ler arquivos SQL externos que estou chamando. 
# As conexões estão em arquivos externos
def read_sql_file(filename):
    sql_path = os.path.join(current_dir, filename)
    with open(sql_path, 'r', encoding='utf-8') as file:
        return file.read()

#%% 📄 Função para executar SQL com SQLAlchemy (Executar sql mais estruturado)
def execute_sql_query(connection, query):
    with connection.connect() as conn:
        result = conn.execute(text(query))

        if result.returns_rows:
            rows = result.fetchall()
            df = pd.DataFrame(rows, columns=result.keys())
            return df
        else:
            print("⚠️ A query não retornou dados.")
            return pd.DataFrame()

#%% 🔍 Executar base_atweb.sql
query_atweb = read_sql_file('base_atweb.sql')
df_atweb = execute_sql_query(conn_dw, query_atweb)

print("\n📦 Dados - base_atweb.sql")
print(df_atweb.head())

#%% 🔍 Executar base_OS_defeitos.sql
query_os_defeitos = read_sql_file('base_OS_defeitos.sql')
df_os_defeitos = execute_sql_query(conn_dw, query_os_defeitos)

print("\n📦 Dados - base_OS_defeitos.sql")
print(df_os_defeitos.head())
print("\n📦 Dados - base_OS_defeitos.sql")
print(df_os_defeitos.tail())

#%% 🔍 Executar base_postos_atweb.sql
query_postos = read_sql_file('base_postos_atweb.sql')
df_postos = execute_sql_query(conn_dw, query_postos)

print("\n📦 Dados - base_postos_atweb.sql")
print(df_postos.head())

#%% 🔍 Executar base_materiais.sql
query_materiais = read_sql_file('base_materiais.sql')
df_materiais = execute_sql_query(conn_dw, query_materiais)

print("\n📦 Dados - base_materiais.sql")
print(df_materiais.head())

#%% 🚪 Fechar conexões
conn_op.dispose()
conn_dw.dispose()
conn_pbi.dispose()

print("\n🔚 Conexões fechadas.")

#%%  Informações sobre as tabelas
df_atweb.info()
df_os_defeitos.info()
df_postos.info()
df_materiais.info()
#%%
print(df_postos.head())
#%%  Renomeando variáveis tabela ATWEB
df_atweb = df_atweb.rename(columns={df_atweb.columns[0]: 'num_os',
                                    df_atweb.columns[1]: 'dt_os',
                                    df_atweb.columns[2]: 'pedido',
                                    df_atweb.columns[3]: 'dt_pedido',
                                    df_atweb.columns[4]: 'pedido_sap',
                                    df_atweb.columns[5]: 'dt_pedido_sap',
                                    df_atweb.columns[6]: 'remesssa',
                                    df_atweb.columns[7]: 'dt_remessa',
                                    df_atweb.columns[8]: 'nf',
                                    df_atweb.columns[9]: 'dt_nf',
                                    df_atweb.columns[10]: 'tp_pedido',
                                    df_atweb.columns[11]: 'tp_frete',
                                    df_atweb.columns[12]: 'st_fatura',
                                    df_atweb.columns[13]: 'id_material',
                                    df_atweb.columns[14]: 'qtde',
                                    df_atweb.columns[15]: 'vlr_mercadoria',
                                    df_atweb.columns[16]: 'vlr_frete',
                                    df_atweb.columns[17]: 'vlr_pedido',
                                    df_atweb.columns[18]: 'id_transportadora',
                                    df_atweb.columns[19]: 'id_posto'})

#%%  Renomeando variáveis tabela ATWEB
df_os_defeitos = df_os_defeitos.rename(columns={df_os_defeitos.columns[0]: 'num_os',
                                    df_os_defeitos.columns[1]: 'st_os',
                                    df_os_defeitos.columns[2]: 'origem_os',
                                    df_os_defeitos.columns[3]: 'dt_os',
                                    df_os_defeitos.columns[4]: 'dt_conserto',
                                    df_os_defeitos.columns[5]: 'dt_conclusao_os',
                                    df_os_defeitos.columns[6]: 'defeito_reclamado',
                                    df_os_defeitos.columns[7]: 'finalidade',
                                    df_os_defeitos.columns[8]: 'defeito_constatado'})

#%%  Renomeando variáveis tabela ATWEB
df_postos = df_postos.rename(columns={df_postos.columns[0]: 'id_posto',
                                    df_postos.columns[1]: 'cnpj',
                                    df_postos.columns[2]: 'razao_social',
                                    df_postos.columns[3]: 'nome_fantasia',
                                    df_postos.columns[4]: 'telefone',
                                    df_postos.columns[5]: 'cidade',
                                    df_postos.columns[6]: 'uf',
                                    df_postos.columns[7]: 'pais',
                                    df_postos.columns[8]: 'st_posto'})

#%%  Renomeando variáveis tabela materiais
df_materiais = df_materiais.rename(columns={df_materiais.columns[0]: 'id_material',
                                    df_materiais.columns[1]: 'descricao',
                                    df_materiais.columns[2]: 'modelo',
                                    df_materiais.columns[3]: 'tp_material',
                                    df_materiais.columns[4]: 'linha',
                                    df_materiais.columns[5]: 'linha2'})

#%%  Conferindo novos nomes das variáveis
df_atweb.info()
df_os_defeitos.info()
df_postos.info()
df_materiais.info()


#%% Valores unicos em Variáveis
print(
    'Status de fatura:\n', ', '.join(map(str, df_atweb['st_fatura'].dropna().unique())), '\n',
    'Tipo de pedido:\n', ', '.join(map(str, df_atweb['tp_pedido'].dropna().unique())), '\n',
    'Status OS:\n', ', '.join(map(str, df_os_defeitos['st_os'].dropna().unique())), '\n',
    'Origem OS:\n', ', '.join(map(str, df_os_defeitos['origem_os'].dropna().unique())), '\n',
    'Defeitos constatados:\n', ', '.join(map(str, df_os_defeitos['defeito_constatado'].dropna().unique())), '\n',
    'Finalidade:\n', ', '.join(map(str, df_os_defeitos['finalidade'].dropna().unique())), '\n',
    'Status Posto:\n', ', '.join(map(str, df_postos['st_posto'].dropna().unique()))
)

# %% Seleção de variáveis da tabela ATWEB

cols_atweb = [
    'num_os',
    'pedido',
    'dt_pedido',
    'tp_pedido',
    'st_fatura',
    'id_material',
    'qtde',
    'vlr_mercadoria',
    'vlr_frete',
    'vlr_pedido',
    'id_posto'
]

indices_atweb = [df_atweb.columns.get_loc(col) for col in cols_atweb]
df_atweb_fato = df_atweb.iloc[:, indices_atweb]
print(df_atweb_fato.head())
# %% Seleção de variáveis da tabela OS_DEFEITOS

cols_os = [
    'num_os',
    'st_os',
    'origem_os',
    'dt_os',
    'dt_conserto',
    'dt_conclusao_os',
    'finalidade',
    'defeito_constatado'
]

indices_os = [df_os_defeitos.columns.get_loc(col) for col in cols_os]
df_os_dim = df_os_defeitos.iloc[:, indices_os]
print(df_os_dim.head())
# %% Seleção de variáveis da tabela POSTOS  

cols_postos = [
    'id_posto',
    'cidade',
    'uf',
    'st_posto'
]

indices_postos = [df_postos.columns.get_loc(col) for col in cols_postos]
df_postos_dim = df_postos.iloc[:, indices_postos]
print(df_postos_dim.head())

# %% Seleção de variáveis da tabela materiais

cols_materiais = [
    'id_material',
    'descricao',
    'modelo',
    'linha'
]

indices_materiais = [df_materiais.columns.get_loc(col) for col in cols_materiais]
df_materiais_dim = df_materiais.iloc[:, indices_materiais]
print(df_materiais_dim.head())

#%%  Conferencia tabelas limpas
df_atweb_fato.info()
df_os_dim.info()
df_postos_dim.info()
df_materiais_dim.info()

# %% consultar tabela atweb_fato sem informação de posto

df_atweb_sem_posto = df_atweb_fato[df_atweb_fato['id_posto'].isna()]

print(
    'Tipo de pedido:\n', ', '.join(map(str, df_atweb_sem_posto['tp_pedido'].dropna().unique())), '\n',
    'Status da fatura:\n', ', '.join(map(str, df_atweb_sem_posto['st_fatura'].dropna().unique())), '\n',
    'Numeros de OS:\n', ', '.join(map(str, df_atweb_sem_posto['num_os'].dropna().unique())), '\n'
)

# %%
df_atweb_com_posto = df_atweb_fato[df_atweb_fato['id_posto'].notna()]

print(
    'Tipo de pedido:\n', ', '.join(map(str, df_atweb_com_posto['tp_pedido'].dropna().unique())), '\n',
    'Status da fatura:\n', ', '.join(map(str, df_atweb_com_posto['st_fatura'].dropna().unique())), '\n',
    'Numeros de OS:\n', ', '.join(map(str, df_atweb_com_posto['num_os'].dropna().unique())), '\n'
)

# %%
