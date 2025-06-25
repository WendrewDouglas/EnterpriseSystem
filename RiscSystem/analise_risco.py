#%%
import os
import sys
import pandas as pd
import numpy as np
from sqlalchemy import text

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
#%% 📄 Função para ler arquivos SQL
def read_sql_file(filename):
    sql_path = os.path.join(current_dir, filename)
    with open(sql_path, 'r', encoding='utf-8') as file:
        return file.read()

#%% 📄 Função para executar SQL com SQLAlchemy (Alternativa 1)
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

#%% 🔍 Executar base_postos_atweb.sql
query_postos = read_sql_file('base_postos_atweb.sql')
df_postos = execute_sql_query(conn_dw, query_postos)

print("\n📦 Dados - base_postos_atweb.sql")
print(df_postos.head())

#%% 🔍 Função de Análise Exploratória Completa
def analise_exploratoria(df, nome="DataFrame"):
    print(f"\n{'='*50}")
    print(f"🔍 📊 Análise Exploratória — {nome}")
    print(f"{'='*50}\n")
    
    # Dimensão
    print(f"✅ Dimensão: {df.shape[0]} linhas e {df.shape[1]} colunas\n")
    
    # Tipos de dados
    print("🧠 Tipos de Dados:")
    print(df.dtypes)
    print("\n")
    
    # Valores nulos
    print("🚨 Valores Nulos por coluna:")
    print(df.isnull().sum())
    print("\n")
    
    # Estatísticas descritivas
    print("📈 Estatísticas Descritivas (Numéricas):")
    print(df.describe().T)
    print("\n")
    
    # Colunas categóricas
    obj_cols = df.select_dtypes(include=['object', 'category']).columns
    if len(obj_cols) > 0:
        print("📑 Análise de Colunas Categóricas:")
        for col in obj_cols:
            print(f"\nColuna: {col}")
            print(df[col].value_counts(dropna=False))
    else:
        print("📑 Não há colunas categóricas.")
    
    print("\n✅ 🔍 Fim da análise para", nome)
    print(f"\n{'='*50}")

#%% 🚀 Executar EDA nas 3 bases
analise_exploratoria(df_atweb, "Base ATWEB")
analise_exploratoria(df_os_defeitos, "Base OS Defeitos")
analise_exploratoria(df_postos, "Base Postos")

#%% 🧠 Função de pré-processamento geral
def preprocess_df(df, nome="DataFrame"):

    print(f"\n🚀 Pré-processamento iniciado para {nome}")

    # 📅 Conversão de datas
    data_cols = [col for col in df.columns if "data" in col.lower()]
    for col in data_cols:
        df[col] = pd.to_datetime(df[col], errors="coerce")

    # 🔢 Conversão de valores numéricos
    num_cols = ["Qtde", "Valor_Mercadoria", "Valor_Frete", "Valor_Pedido"]
    for col in num_cols:
        if col in df.columns:
            df[col] = (df[col]
                       .astype(str)
                       .str.replace(",", ".")
                       .str.replace(" ", "")
                       .astype(float, errors="ignore"))

    # 🔠 Normalização de textos
    obj_cols = df.select_dtypes(include="object").columns
    for col in obj_cols:
        df[col] = df[col].astype(str).str.upper().str.strip().replace("NAN", np.nan)

    # 🏷️ Tratamento de nulos
    for col in df.columns:
        if df[col].dtype in [np.float64, np.int64]:
            df[col] = df[col].fillna(-999)  # Usado como marcador especial
        elif df[col].dtype == 'datetime64[ns]':
            df[col] = df[col]  # Mantém como NaT para análise posterior
        else:
            df[col] = df[col].fillna("SEM_INFORMACAO")

    # 🔍 Tratamento de coluna Estado (Exemplo específico)
    if "ESTADO" in df.columns:
        df["ESTADO"] = df["ESTADO"].str.extract(r"([A-Z]{2})", expand=False).fillna("SEM_INFORMACAO")

    # 🚫 Tratamento de duplicados
    linhas_antes = df.shape[0]
    df = df.drop_duplicates()
    linhas_depois = df.shape[0]
    print(f"✔️ Removidas {linhas_antes - linhas_depois} linhas duplicadas.")

    print(f"✅ Pré-processamento concluído para {nome}")
    return df

#%% 🚀 Aplicar pré-processamento
df_atweb_clean = preprocess_df(df_atweb, "Base ATWEB")
df_os_defeitos_clean = preprocess_df(df_os_defeitos, "Base OS Defeitos")
df_postos_clean = preprocess_df(df_postos, "Base Postos")








#%% 🚪 Fechar conexões
conn_op.dispose()
conn_dw.dispose()
conn_pbi.dispose()

print("\n🔚 Conexões fechadas.")
# %%
