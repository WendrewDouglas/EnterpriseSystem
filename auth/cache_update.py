import os
import sys

# Adiciona o diretório raiz do projeto ao sys.path
# Nesse caso, sobe um nível para encontrar a pasta "includes"
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

import pandas as pd
from datetime import datetime
from includes.db_connection import get_db_connection
import matplotlib.pyplot as plt
import seaborn as sns

def update_cache():
    engine = get_db_connection()
    
    # Consulta completa; ajuste a query conforme necessário.
    query = "SELECT * FROM ZSAP..V_CONTAS_RECEBER_SAP"
    print("Iniciando consulta ao banco de dados...")
    df = pd.read_sql(query, engine)
    
    # Processa os dados: converte a coluna de datas e remove registros inválidos
    df['DTVENCIMENTO'] = pd.to_datetime(df['DTVENCIMENTO'], errors='coerce')
    df = df.dropna(subset=['DTVENCIMENTO'])
    
    # Salva o DataFrame em um arquivo HDF5
    output_file = 'cache_data.h5'
    df.to_hdf(output_file, key='dados', mode='w')
    
    print(f"Cache atualizado com sucesso em {datetime.now()}")

if __name__ == '__main__':
    update_cache()
