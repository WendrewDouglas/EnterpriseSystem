# %% Importar bibliotecas
import sys
import os
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns

# %% Criar conexão com o banco
sys.path.append(os.path.abspath("../includes"))
from db_connection import get_db_connection
engine = get_db_connection()

# %% Executando queries corretamente
query_titulos = "SELECT * FROM ZSAP..V_CONTAS_RECEBER_SAP"

df_titulos = pd.read_sql(query_titulos, engine)



# %% Exibir primeiras linhas
print("Dados de titulos:")
print(df_titulos.head())

# %%
print("Estatísticas Descritivas:")
print(df_titulos.describe())

