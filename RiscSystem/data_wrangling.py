#%% Instalando os pacotes

!pip install pandas
!pip install numpy
!pip install matplotlib
!pip install seaborn
!pip install xlrd
!pip install factor_analyzer
!pip install sympy
!pip install scipy
!pip install plotly
!pip install pingouin
!pip install pyshp
!pip install -q translate
!pip install -q nltk
!pip install -q wordcloud
!pip install -q googletrans
!pip install -q deep_translator
!pip install -q textblob 
!pip install unidecode
!pip install spacy

# %% Dependências
import nltk
nltk.download('stopwords')
nltk.download('rslp')
nltk.download('vader_lexicon')
nltk.download('punkt')

import spacy
import pandas as pd
import string
from collections import Counter
from nltk.corpus import stopwords
from nltk.stem import RSLPStemmer
from unidecode import unidecode
from wordcloud import WordCloud
import matplotlib.pyplot as plt
from tqdm import tqdm
import pickle
import os
import sys
import numpy as np
from sqlalchemy import text
import seaborn as sns
import pingouin as pg
import plotly.express as px
import plotly.graph_objects as go
import deep_translator
from factor_analyzer import FactorAnalyzer
from factor_analyzer.factor_analyzer import calculate_bartlett_sphericity
import plotly.io as pio
pio.renderers.default = 'browser'
from sklearn.preprocessing import StandardScaler
from sklearn.ensemble import IsolationForest


#%% 📦 Definir caminhos para importar os arquivos de conexão
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
try:
    conn_op = conn_operacional()
    conn_dw = conn_dw()
    conn_pbi = conn_pwrbi()
    print("✅ Conexões com os três bancos foram realizadas com sucesso!")
except Exception as e:
    print(f"❌ Falha na conexão: {e}")
    sys.exit(1)

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

#%% 🔍 Executar base_pedidos.sql
query_pedidos = read_sql_file('base_atweb.sql')
df_pedidos = execute_sql_query(conn_dw, query_pedidos)

print("\n📦 Dados - base_pedidos.sql")
print(df_pedidos.head())
print("\n📦 Informações")
df_pedidos.info()

#%% 🔍 Executar base_OS_defeitos.sql
query_os = read_sql_file('base_OS_defeitos.sql')
df_os = execute_sql_query(conn_dw, query_os)

print("\n📦 Dados - base_OS_defeitos.sql")
print(df_os.head())
print("\n📦 Informações")
df_os.info()

#%% 🔍 Executar base_postos_atweb.sql
query_postos = read_sql_file('base_postos_atweb.sql')
df_postos = execute_sql_query(conn_dw, query_postos)

print("\n📦 Dados - base_postos_atweb.sql")
print(df_postos.head())
print("\n📦 Informações")
df_postos.info()

#%% 🔍 Executar base_materiais.sql
query_materiais = read_sql_file('base_materiais.sql')
df_materiais = execute_sql_query(conn_dw, query_materiais)

print("\n📦 Dados - base_materiais.sql")
print(df_materiais.head())
print("\n📦 Informações")
df_materiais.info()

#%% 🔍 Executar base_consumidor.sql
query_consumidor = read_sql_file('base_consumidor.sql')
df_consumidor = execute_sql_query(conn_dw, query_consumidor)

print("\n📦 Dados - base_consumidor.sql")
print(df_consumidor.head())
print("\n📦 Informações")
df_consumidor.info()

#%% 🔍 Executar base_revendedor.sql
query_revendedor = read_sql_file('base_revendedor.sql')
df_revendedor = execute_sql_query(conn_dw, query_revendedor)

print("\n📦 Dados - base_revendedor.sql")
print(df_revendedor.head())
print("\n📦 Informações")
df_revendedor.info()

#%% 🚪 Fechar conexões
conn_op.dispose()
conn_dw.dispose()
conn_pbi.dispose()

print("\n🔚 Conexões fechadas.")

#%%  Informações sobre as tabelas
df_pedidos.info()
df_os.info()
df_postos.info()
df_materiais.info()
df_consumidor.info()
df_revendedor.info()

#%%  Renomeando variáveis tabela ATWEB
df_pedidos = df_pedidos.rename(columns={df_pedidos.columns[0]: 'num_os',
                                    df_pedidos.columns[1]: 'dt_os',
                                    df_pedidos.columns[2]: 'pedido',
                                    df_pedidos.columns[3]: 'dt_pedido',
                                    df_pedidos.columns[4]: 'pedido_sap',
                                    df_pedidos.columns[5]: 'dt_pedido_sap',
                                    df_pedidos.columns[6]: 'remesssa',
                                    df_pedidos.columns[7]: 'dt_remessa',
                                    df_pedidos.columns[8]: 'nf',
                                    df_pedidos.columns[9]: 'dt_nf',
                                    df_pedidos.columns[10]: 'tp_pedido',
                                    df_pedidos.columns[11]: 'tp_frete',
                                    df_pedidos.columns[12]: 'st_fatura',
                                    df_pedidos.columns[13]: 'id_material',
                                    df_pedidos.columns[14]: 'qtde',
                                    df_pedidos.columns[15]: 'vlr_mercadoria',
                                    df_pedidos.columns[16]: 'vlr_frete',
                                    df_pedidos.columns[17]: 'vlr_pedido',
                                    df_pedidos.columns[18]: 'id_transportadora',
                                    df_pedidos.columns[19]: 'id_posto'})

#%%  Renomeando variáveis tabela OS
df_os = df_os.rename(columns={df_os.columns[0]: 'num_os',
                                    df_os.columns[1]: 'origem',
                                    df_os.columns[2]: 'status',
                                    df_os.columns[3]: 'dt_abertura',
                                    df_os.columns[4]: 'dt_fechamento',
                                    df_os.columns[5]: 'finalidade',
                                    df_os.columns[6]: 'id_posto',
                                    df_os.columns[7]: 'id_consumidor',
                                    df_os.columns[8]: 'serie_produto',
                                    df_os.columns[9]: 'produto',
                                    df_os.columns[10]: 'peca',
                                    df_os.columns[11]: 'qtd_peca',
                                    df_os.columns[12]: 'qtd_estoque',
                                    df_os.columns[13]: 'qtd_pedido',
                                    df_os.columns[14]: 'dt_fabric_prod',
                                    df_os.columns[15]: 'id_revendedor',
                                    df_os.columns[16]: 'dt_compra_prod',
                                    df_os.columns[17]: 'defeito_reclamado',
                                    df_os.columns[18]: 'defeito_constatado',
                                    df_os.columns[19]: 'pedido',
                                    df_os.columns[20]: 'tp_pedido',
                                    df_os.columns[21]: 'st_pedido',
                                    df_os.columns[22]: 'user_abertura',
                                    df_os.columns[23]: 'qtd_adicional',
                                    df_os.columns[24]: 'vlr_unit_adicional',
                                    df_os.columns[25]: 'vlr_total_adicional',
                                    df_os.columns[26]: 'descr_adicional',
                                    df_os.columns[27]: 'relato'})

#%%  Renomeando variáveis tabela POSTOS
df_postos = df_postos.rename(columns={df_postos.columns[0]: 'id_posto',
                                    df_postos.columns[1]: 'cnpj',
                                    df_postos.columns[2]: 'razao_social',
                                    df_postos.columns[3]: 'nome_fantasia',
                                    df_postos.columns[4]: 'telefone',
                                    df_postos.columns[5]: 'cidade',
                                    df_postos.columns[6]: 'uf',
                                    df_postos.columns[7]: 'pais',
                                    df_postos.columns[8]: 'st_posto'})

#%%  Renomeando variáveis tabela MATERIAIS
df_materiais = df_materiais.rename(columns={df_materiais.columns[0]: 'id_material',
                                    df_materiais.columns[1]: 'descricao',
                                    df_materiais.columns[2]: 'modelo',
                                    df_materiais.columns[3]: 'tp_material',
                                    df_materiais.columns[4]: 'linha',
                                    df_materiais.columns[5]: 'linha2'})

#%%  Renomeando variáveis tabela CONSUMIDOR
df_consumidor = df_consumidor.rename(columns={df_consumidor.columns[0]: 'id_consumidor',
                                    df_consumidor.columns[1]: 'cpf_cnpj',
                                    df_consumidor.columns[2]: 'nome',
                                    df_consumidor.columns[3]: 'telefone',
                                    df_consumidor.columns[4]: 'pais',
                                    df_consumidor.columns[5]: 'uf',
                                    df_consumidor.columns[6]: 'cidade',
                                    df_consumidor.columns[7]: 'bairro',
                                    df_consumidor.columns[8]: 'telefone2'})

#%%  Renomeando variáveis tabela REVENDEDOR
df_revendedor = df_revendedor.rename(columns={df_revendedor.columns[0]: 'id_revendedor',
                                    df_revendedor.columns[1]: 'cnpj',
                                    df_revendedor.columns[2]: 'razao_social',
                                    df_revendedor.columns[3]: 'telefone',
                                    df_revendedor.columns[4]: 'pais',
                                    df_revendedor.columns[5]: 'uf',
                                    df_revendedor.columns[6]: 'cidade',
                                    df_revendedor.columns[7]: 'bairro'})

#%%  Conferindo novos nomes das variáveis
df_pedidos.info()
df_os.info()
df_postos.info()
df_materiais.info()
df_consumidor.info()
df_revendedor.info()

# %% Seleção de variáveis da tabela ATWEB

cols_os = [
    'num_os',
    'origem',
    'status',
    'dt_abertura',
    'dt_fechamento',
    'finalidade',
    'id_posto',
    'id_consumidor',
    'serie_produto',
    'produto',
    'peca',
    'qtd_peca',
    'qtd_pedido',
    'dt_fabric_prod',
    'id_revendedor',
    'dt_compra_prod',
    'defeito_reclamado',
    'defeito_constatado',
    'pedido',
    'user_abertura',
    'qtd_adicional',
    'vlr_unit_adicional',
    'descr_adicional',
    'relato'
]

indices_os = [df_os.columns.get_loc(col) for col in cols_os]
df_os = df_os.iloc[:, indices_os]
print(df_os.head())
# %% Seleção de variáveis da tabela OS

cols_pedidos = [
    'num_os',
    'pedido',
    'pedido_sap',
    'dt_pedido',
    'tp_pedido',
    'nf',
    'st_fatura',
    'id_material',
    'qtde',
    'vlr_mercadoria',
    'vlr_pedido',
    'id_posto'
]

indices_pedidos = [df_pedidos.columns.get_loc(col) for col in cols_pedidos]
df_pedidos = df_pedidos.iloc[:, indices_pedidos]
print(df_pedidos.head())
# %% Seleção de variáveis da tabela POSTOS  

cols_postos = [
    'id_posto',
    'cidade',
    'uf',
    'st_posto'
]

indices_postos = [df_postos.columns.get_loc(col) for col in cols_postos]
df_postos = df_postos.iloc[:, indices_postos]
print(df_postos.head())

# %% Seleção de variáveis da tabela consumidor

cols_materiais = [
    'id_material',
    'descricao',
    'modelo',
    'linha'
]

indices_materiais = [df_materiais.columns.get_loc(col) for col in cols_materiais]
df_materiais = df_materiais.iloc[:, indices_materiais]
print(df_materiais.head())

# %% Seleção de variáveis da tabela materiais

cols_consumidor = [
    'id_consumidor',
    'cidade',
    'uf'
]

indices_consumidor = [df_consumidor.columns.get_loc(col) for col in cols_consumidor]
df_consumidor = df_consumidor.iloc[:, indices_consumidor]
print(df_consumidor.head())


# %% Seleção de variáveis da tabela revendedor

cols_revendedor = [
    'id_revendedor',
    'cidade',
    'uf'
]

indices_revendedor = [df_revendedor.columns.get_loc(col) for col in cols_revendedor]
df_revendedor = df_revendedor.iloc[:, indices_revendedor]
print(df_revendedor.head())

#%%  Conferencia tabelas limpas
df_pedidos.info()
df_os.info()
df_postos.info()
df_materiais.info()
df_consumidor.info()
df_revendedor.info()

# Dicionário com seus DataFrames
dfs = {
    'pedidos': df_pedidos,
    'os': df_os,
    'postos': df_postos,
    'materiais': df_materiais,
    'consumidor': df_consumidor,
    'revendedor': df_revendedor
}

# Para cada tabela, exibe dtypes e número de valores distintos por coluna
for name, df in dfs.items():
    print(f"\n=== DataFrame: {name} ===")
    
    # 1) Tipos das colunas
    print("\nColunas e tipos:")
    print(df.dtypes)
    
    # 2) Número de valores únicos por coluna
    print("\nValores únicos por coluna:")
    print(df.nunique())
    
    # 3) (Opcional) resumo em um DataFrame para facilitar leitura
    summary = pd.DataFrame({
        'dtype': df.dtypes,
        'n_unique': df.nunique(),
        'n_null': df.isna().sum()
    })
    print("\nResumo combinado (dtype, n_unique, n_null):")
    display(summary)

#%% 1. Converter tipos para chaves de junção consistentes

df_os['pedido'] = df_os['pedido'].astype('Int64')
df_pedidos['pedido'] = df_pedidos['pedido'].astype('Int64')
df_pedidos['num_os'] = df_pedidos['num_os'].astype('Int64')
df_materiais['id_material'] = df_materiais['id_material'].astype(str)

#%% 2. Merge de df_os com df_postos
df_ordem_servico = df_os.merge(
    df_postos,
    on='id_posto',
    how='left',
    suffixes=('', '_posto')
)

#%% 3. Merge com df_consumidor
df_ordem_servico = df_ordem_servico.merge(
    df_consumidor,
    on='id_consumidor',
    how='left',
    suffixes=('', '_consumidor')
)

#%% 4. Merge com df_revendedor
df_ordem_servico = df_ordem_servico.merge(
    df_revendedor,
    on='id_revendedor',
    how='left',
    suffixes=('', '_revendedor')
)

#%% 5. Merge com df_pedidos usando num_os + pedido
df_ordem_servico = df_ordem_servico.merge(
    df_pedidos,
    on=['num_os', 'pedido', 'id_posto'],
    how='left',
    suffixes=('', '_pedido')
)

#%% 6. Merge com df_materiais via id_material (vindos de df_pedidos)
df_ordem_servico = df_ordem_servico.merge(
    df_materiais,
    on='id_material',
    how='left',
    suffixes=('', '_material')
)

#%% 7. Revisão final
# lista de colunas de ID para excluir do df_ordem_servico
ids_para_remover = [
    "id_posto",
    "id_consumidor",
    "id_revendedor",
    "pedido",
    "id_material",
    "serie_produto"
]

# drop
df_ordem_servico = df_ordem_servico.drop(columns=ids_para_remover)

print("\n✅ IDs removidos do df_ordem_servico, exceto num_os (mantido para posterior mapeamento)")
print(df_ordem_servico.info())

# Agora df_ordem_servico contém todas as colunas de OS 
# e as informações de posto, consumidor, revendedor, pedidos e materiais.
# %% Converter colunas em DATETIME

date_cols = ['dt_abertura','dt_fechamento','dt_fabric_prod','dt_compra_prod','dt_pedido']
for c in date_cols:
    df_ordem_servico[c] = pd.to_datetime(df_ordem_servico[c], errors='coerce', dayfirst=True)

for c in date_cols:
    pct_nat = df_ordem_servico[c].isna().mean()*100
    print(f"{c} → {pct_nat:.2f}% valores faltantes")


# %% Limpeza e conversão de numéricos

# colunas que queremos normalizar de object → float
num_cols = [
    'vlr_mercadoria','vlr_pedido','qtd_pedido','qtd_peca',
    'qtd_adicional','vlr_unit_adicional'
]

for c in num_cols:
    # 1) passa tudo para string (substitui None/NaN por 'nan')
    s = df_ordem_servico[c].astype(str)
    
    # 2) remove caracteres que não são dígitos, ponto ou vírgula
    s = s.str.replace('[^0-9,.-]', '', regex=True)
    
    # 3) padroniza vírgula → ponto
    s = s.str.replace(',', '.', regex=False)
    
    # 4) converte pra float, forçando erros a NaN
    df_ordem_servico[c] = pd.to_numeric(s, errors='coerce')

# confira
print(df_ordem_servico[num_cols].dtypes)
print(df_ordem_servico[num_cols].describe())
# %% Tratamento de valores ausentes

df_ordem_servico['aberta'] = df_ordem_servico['dt_fechamento'].isna()
cat_cols = df_ordem_servico.select_dtypes('object').columns
df_ordem_servico[cat_cols] = df_ordem_servico[cat_cols].fillna('SEM_DADO')

# %% Uniformização de categorias e redução de cardinalidade

df_ordem_servico['dias_ate_abertura'] = (df_ordem_servico['dt_abertura'] 
                                        - df_ordem_servico['dt_compra_prod']).dt.days
df_ordem_servico['tempo_resolucao']  = (df_ordem_servico['dt_fechamento']
                                        - df_ordem_servico['dt_abertura']).dt.days
df_ordem_servico['idade_produto']    = (df_ordem_servico['dt_abertura']
                                        - df_ordem_servico['dt_fabric_prod']).dt.days


# %% Detalhes das variáveis

def detalhes_variaveis(df):
    resumo = []

    for coluna in df.columns:
        qtd_unicos = df[coluna].nunique()
        pct_missing = df[coluna].isnull().mean() * 100
        top_5_freq = df[coluna].value_counts(dropna=False).head(5)

        resumo.append({
            'variavel': coluna,
            'tipo': df[coluna].dtype,
            'qtd_unicos': qtd_unicos,
            'pct_missing': pct_missing,
            'top_5_valores': top_5_freq.to_dict()
        })

    return pd.DataFrame(resumo).sort_values(by='qtd_unicos', ascending=False)

# Executar a função no seu DataFrame atual
df_detalhes = detalhes_variaveis(df_ordem_servico)

# Exibir resultados detalhados
pd.set_option('display.max_colwidth', None)
print(df_detalhes)

# %% remover a coluna de série/lote

df_ordem_servico = df_ordem_servico.drop(columns=['serie_produto'])

print("✅ Coluna 'serie_produto' removida do DataFrame.")



# %% -------TEXT MINING PARA CATEGORIAS DE RECLAMAÇÕES-------------------------


# carregar modelo spaCy
nlp = spacy.load("pt_core_news_sm")

# lista de stopwords
stop_words = set(stopwords.words("portuguese"))

# adicionar suas stopwords personalizadas
stop_words.update(["so", "ta", "pra", "pro", "to", "tá", "né", "eh", "ai", "aí", "vc", "vcs", "pq", "q"])

# inicializa stemmer
stemmer = RSLPStemmer()

# %% Função de pré-processamento completa com PRINTS
def preprocess_text(text):
    # normalização inicial
    text = unidecode(text.lower())
    text = text.translate(str.maketrans("", "", string.punctuation))
    words = text.split()
    
    # remove stopwords
    words = [w for w in words if w not in stop_words]
    
    # aplicar **apenas lemmatização** spaCy
    lemmatized_words = []
    for word in words:
        doc = nlp(word)
        lemmatized_words.append(doc[0].lemma_)
    
    # aplicar regras de junção
    treated_words = []
    skip_next = False
    
    for idx, word in enumerate(lemmatized_words):
        if skip_next:
            skip_next = False
            continue

        if word == 'nao' and idx + 1 < len(lemmatized_words):
            combined = f"nao_{lemmatized_words[idx + 1]}"
            treated_words.append(combined)
            skip_next = True
        elif word == 'fica' and idx + 1 < len(lemmatized_words):
            combined = f"fica_{lemmatized_words[idx + 1]}"
            treated_words.append(combined)
            skip_next = True
        elif word == 'faz' and idx + 1 < len(lemmatized_words):
            combined = f"faz_{lemmatized_words[idx + 1]}"
            treated_words.append(combined)
            skip_next = True
        elif word == 'cheirando' and idx + 1 < len(lemmatized_words):
            combined = f"cheirando_{lemmatized_words[idx + 1]}"
            treated_words.append(combined)
            skip_next = True
        elif word == 'travado' and idx - 1 >= 0:
            combined = f"{lemmatized_words[idx - 1]}_travado"
            treated_words.append(combined)
        else:
            treated_words.append(word)
    
    # segunda varredura para 'nao_esta'
    final_words = []
    skip_next = False
    for idx, word in enumerate(treated_words):
        if skip_next:
            skip_next = False
            continue

        if word == 'nao_esta' and idx + 1 < len(treated_words):
            combined = f"nao_esta_{treated_words[idx + 1]}"
            final_words.append(combined)
            skip_next = True
        else:
            final_words.append(word)
    
    return final_words


# %% Gerar tokens para defeito_reclamado e defeito_constatado com a mesma função

print("\n========== ETAPA 1: PRÉ-PROCESSAMENTO EM BATCHS COM AVISO SIMPLES DE 1% ==========")

tokens_reclamado = []
tokens_constatado = []

# dados sem NA
subset_reclamado = df_ordem_servico['defeito_reclamado'].fillna('')
subset_constatado = df_ordem_servico['defeito_constatado'].fillna('')

total = len(df_ordem_servico)
batch_size = 1000
percent_step = max(int(total * 0.01), 1)  # a cada 1%

for start in range(0, total, batch_size):
    end = min(start + batch_size, total)
    
    bloco_reclamado = subset_reclamado.iloc[start:end]
    bloco_constatado = subset_constatado.iloc[start:end]
    
    for idx, (reclamacao, constatado) in enumerate(zip(bloco_reclamado, bloco_constatado), start+1):
        tokens_r = preprocess_text(reclamacao)
        tokens_c = preprocess_text(constatado)
        
        tokens_reclamado.append(tokens_r)
        tokens_constatado.append(tokens_c)
        
        if idx % percent_step == 0:
            pct = int((idx/total)*100)
            print(f"Progresso: {pct}% concluído")

print("\n✅ Tokenização completa para reclamado e constatado.")

# %% Criar variável de contagem de tokens coincidentes
print("\n✅ Gerando variável de interseção de tokens entre reclamado e constatado...")

intersecoes = []

for idx in range(len(df_ordem_servico)):
    reclamado = set(tokens_reclamado[idx])
    constatado = set(tokens_constatado[idx])
    
    # contar quantos tokens batem
    qtd_match = len(reclamado & constatado)
    intersecoes.append(qtd_match)

# adiciona ao dataframe
df_comparacao_tokens = df_ordem_servico.copy()
df_comparacao_tokens['qtd_tokens_match'] = intersecoes

print("\n✅ Variável 'qtd_tokens_match' criada e incluída no novo dataframe.")
print(df_comparacao_tokens[['defeito_reclamado', 'defeito_constatado', 'qtd_tokens_match']].head(10))

# %% Gerar ranking das top 100 keywords - Parte 1
print("\n========== ETAPA 1: PRÉ-PROCESSAMENTO EM BATCHS COM AVISO SIMPLES DE 1% ==========")

tokens_linhas = []

subset = df_ordem_servico['defeito_reclamado'].dropna()
batch_size = 1000
total = len(subset)

percent_step = max(int(total * 0.01), 1)  # avisa a cada 1%

for start in range(0, total, batch_size):
    end = min(start + batch_size, total)
    bloco = subset.iloc[start:end]
    
    for idx, reclamacao in enumerate(bloco, start+1):
        tokens = preprocess_text(reclamacao)
        tokens_linhas.append(tokens)
        
        # apenas mostra percentual, sem dados
        if idx % percent_step == 0:
            pct = int((idx/total)*100)
            print(f"Progresso: {pct}% concluído")
                

# %% Gerar ranking das top 100 keywords - Parte 2
print("\n========== ETAPA 2: RANKEANDO AS TOP 100 ==========")

# achatar a lista de listas
all_words = [word for linha in tokens_linhas for word in linha]

# contar
counter_all = Counter(all_words)
top_100 = [word for word, count in counter_all.most_common(100)]

for i, word in enumerate(top_100, 1):
    print(f"{i:3d}: {word}")

# %% Gerar DataFrame Quantitativo para defeito_reclamado

print("\n========== ETAPA 3: GERANDO DATAFRAME QUANTITATIVO EM BATCHS ==========")

batch_size_qtd = 5000
total_qtd = len(tokens_linhas)
percent_step_qtd = max(int(total_qtd * 0.01), 1)

dfs_partes = []

for start in range(0, total_qtd, batch_size_qtd):
    end = min(start + batch_size_qtd, total_qtd)
    bloco = tokens_linhas[start:end]
    
    linhas_dict = []
    
    for idx, tokens in enumerate(bloco, start+1):
        contagem = {}
        for lema in top_100:
            contagem[lema] = tokens.count(lema)
        linhas_dict.append(contagem)
        
        # progresso
        if idx % percent_step_qtd == 0:
            pct = int((idx/total_qtd)*100)
            print(f"Progresso dataframe: {pct}% concluído")
    
    # dataframe parcial
    df_parte = pd.DataFrame(linhas_dict, index=df_ordem_servico.index[start:end])
    dfs_partes.append(df_parte)

# juntar tudo no final
df_reclame_quantitativo = pd.concat(dfs_partes)

# adicionar colunas extras (sem defeito_reclamado)
colunas_extra = df_ordem_servico.drop(columns=["defeito_reclamado"])
df_reclame_quantitativo = pd.concat([colunas_extra, df_reclame_quantitativo], axis=1)

print("\nJuntamos as variáveis originais (sem defeito_reclamado) com as features quantitativas:")
print(df_reclame_quantitativo.head())

#%% Conferencia
#%% Conferencia
print("\n========== ETAPA DE CONFERÊNCIA DAS FEATURES ==========")

# garantir apenas colunas do top_100 que realmente estão no dataframe
colunas_presentes = [col for col in top_100 if col in df_reclame_quantitativo.columns]

print(f"Total colunas presentes no DataFrame: {len(colunas_presentes)}")

# agora garantir que elas sejam numéricas — removendo colunas com tipo estranho
colunas_numericas = []
colunas_problema = []

for col in colunas_presentes:
    try:
        # tentar conversão segura
        df_reclame_quantitativo[col] = pd.to_numeric(
            df_reclame_quantitativo[col], errors="coerce"
        ).fillna(0).astype(int)
        
        # após conversão, checar se virou numérica
        if pd.api.types.is_numeric_dtype(df_reclame_quantitativo[col]):
            colunas_numericas.append(col)
        else:
            colunas_problema.append(col)
            
    except Exception as e:
        colunas_problema.append(col)
        print(f"⚠️ Problema ao converter a coluna '{col}': {e}")

print(f"\n✅ Total colunas numéricas conferidas e convertidas: {len(colunas_numericas)}")
if colunas_problema:
    print(f"⚠️ Colunas que foram ignoradas por problemas de tipo: {colunas_problema}")

# soma apenas as colunas 100% numéricas
somas = df_reclame_quantitativo[colunas_numericas].sum().sort_values(ascending=False)

print("\n========== SOMA TOTAL POR FEATURE ==========")
for lema, total in somas.items():
    print(f"{lema:20s} → {total} ocorrências")

# conferir tipos finais
print("\n========== TIPOS FINAIS DAS FEATURES NUMÉRICAS ==========")
print(df_reclame_quantitativo[colunas_numericas].dtypes)




# %% Criar a nuvem de palavras com fundo preto
print("\n========== GERANDO WORDCLOUD ==========")

# transformar a Series somas em dict
word_freq = somas.to_dict()

wordcloud = WordCloud(
    width=800,
    height=400,
    background_color="black",
    colormap="cool"
).generate_from_frequencies(word_freq)

# Exibir
plt.figure(figsize=(12, 6))
plt.imshow(wordcloud, interpolation="bilinear")
plt.axis("off")
plt.title("Palavras mais frequentes nos defeitos relatados", fontsize=14, color="white")
plt.show()


# %%
print("\n========== VALORES DISTINTOS POR COLUNA OBJECT ==========")

# filtrar apenas colunas object
colunas_object = df_ordem_servico.select_dtypes(include="object").columns

for col in colunas_object:
    distintos = df_ordem_servico[col].nunique()
    print(f"{col:25s} → {distintos} valores distintos")
# %%
print("\n========== RESUMO DOS VALORES ÚNICOS POR COLUNA OBJECT ==========")

colunas_object = df_ordem_servico.select_dtypes(include="object").columns

for col in colunas_object:
    valores_unicos = df_ordem_servico[col].unique()
    print(f"\nColuna: {col}")
    print(f"Total distintos: {len(valores_unicos)}")
    print(f"Valores: {valores_unicos}")

# %%
print(df_reclame_quantitativo.info())
# %%
# lista de colunas categóricas a transformar
colunas_categoricas = [
    "origem", "status", "finalidade", "defeito_constatado",
    "user_abertura", "st_posto", "tp_pedido", "modelo", "linha"
]

# processar cada uma
for col in colunas_categoricas:
    if col in df_reclame_quantitativo.columns:
        print(f"\nTransformando coluna: {col}")
        
        # criar dummies ignorando 'SEM_DADO'
        dummies = pd.get_dummies(
            df_reclame_quantitativo[col],
            prefix=col,
            prefix_sep='_',
            dtype=int
        )
        
        # remover a dummy 'SEM_DADO' se existir
        col_sem_dado = f"{col}_SEM_DADO"
        if col_sem_dado in dummies.columns:
            dummies = dummies.drop(columns=col_sem_dado)
            print(f" → Ignorando categoria 'SEM_DADO' na coluna {col}")
        
        # anexar ao dataframe
        df_reclame_quantitativo = pd.concat([df_reclame_quantitativo, dummies], axis=1)
        
        # opcionalmente, pode remover a coluna original:
        df_reclame_quantitativo = df_reclame_quantitativo.drop(columns=col)

print("\n✅ Todas as variáveis categóricas foram transformadas em dummies, ignorando 'SEM_DADO'.")

print(df_reclame_quantitativo.head(20))

# %%
print("\n========== LISTA DE COLUNAS E SEUS TIPOS ==========")
for col, tipo in df_reclame_quantitativo.dtypes.items():
    print(f"{col:35s} → {tipo}")


for col, tipo in df_reclame_quantitativo.dtypes.items():
    if tipo != 'object':
        print(f"{col:35s} → {tipo}")
# %%
print("\n========== CRIANDO DATAFRAME APENAS NUMÉRICO ==========")

# selecionar apenas colunas não-object
colunas_numericas = df_reclame_quantitativo.select_dtypes(exclude="object").columns

# criar novo dataframe
df_reclame_numerico = df_reclame_quantitativo[colunas_numericas].copy()

print("\n========== VERIFICANDO COLUNAS DUPLICADAS ==========")

# obter nomes duplicados
duplicadas = df_reclame_numerico.columns[df_reclame_numerico.columns.duplicated()].unique()
print(f"Colunas duplicadas detectadas: {duplicadas}")

for col in duplicadas:
    # localizar todos os índices desta coluna
    indices = [i for i, c in enumerate(df_reclame_numerico.columns) if c == col]
    
    # ver todos os tipos
    for idx in indices:
        tipo = df_reclame_numerico.iloc[:, idx].dtype
        print(f" → coluna '{col}' na posição {idx} tem tipo {tipo}")
        
        # se tipo object, vamos eliminar
        if tipo == 'object':
            df_reclame_numerico.drop(df_reclame_numerico.columns[idx], axis=1, inplace=True)
            print(f" ✔️  coluna '{col}' na posição {idx} (object) foi removida.")
            # break se quiser parar no primeiro:
            break



print(f"✅ Novo dataframe criado: df_reclame_numerico com {df_reclame_numerico.shape[1]} colunas numéricas.")

for col, tipo in df_reclame_numerico.dtypes.items():
    print(f"{col:35s} → {tipo}")

# %%
print("\n========== CRIANDO VARIÁVEIS DE MÊS ==========")

# map de nomes de mês (em português)
nomes_meses = {
    1: 'janeiro', 2: 'fevereiro', 3: 'marco', 4: 'abril',
    5: 'maio', 6: 'junho', 7: 'julho', 8: 'agosto',
    9: 'setembro', 10: 'outubro', 11: 'novembro', 12: 'dezembro'
}

# lista de colunas datetime
colunas_data = [
    'dt_abertura', 'dt_fechamento', 'dt_fabric_prod',
    'dt_compra_prod', 'dt_pedido'
]

for col in colunas_data:
    if col in df_reclame_numerico.columns:
        print(f"Processando coluna de data: {col}")
        # extrair mês
        meses = df_reclame_numerico[col].dt.month.fillna(0).astype(int)
        for mes_num in range(1, 13):
            nome_mes = nomes_meses[mes_num]
            nova_col = f"{col.replace('dt_','').replace('_prod','')}_{nome_mes}"
            df_reclame_numerico[nova_col] = (meses == mes_num).astype(int)

print("\n✅ Variáveis de mês criadas para todas as datas.")


# %%
# lista de colunas datetime originais que você já processou
colunas_data = [
    'dt_abertura', 'dt_fechamento', 'dt_fabric_prod',
    'dt_compra_prod', 'dt_pedido'
]

# remover se existirem
colunas_a_remover = [col for col in colunas_data if col in df_reclame_numerico.columns]

df_reclame_numerico.drop(columns=colunas_a_remover, inplace=True)

print(f"\n✅ Removidas as colunas datetime originais: {colunas_a_remover}")
# %%

for col, tipo in df_reclame_numerico.dtypes.items():
    print(f"{col:35s} → {tipo}")
# %%
df_reclame_numerico.describe()
# %%
# Matriz de correlações

corr = df_reclame_numerico.corr()


# pegar só as combinações únicas (upper triangle, sem repetição)
corr_pairs = (
    corr.where(np.triu(np.ones(corr.shape), k=1).astype(bool))
    .stack()
    .reset_index()
    .rename(columns={0: 'correlation', 'level_0': 'feature_1', 'level_1': 'feature_2'})
)

# ordenar do maior para o menor
corr_pairs = corr_pairs.reindex(corr_pairs['correlation'].abs().sort_values(ascending=False).index)

# filtrar apenas as mais relevantes (> 0.6, por exemplo)
corr_pairs_filtradas = corr_pairs[abs(corr_pairs['correlation']) > 0.6]

# mostrar
print("\n========== MAIORES CORRELAÇÕES ==========")
print(corr_pairs_filtradas)

# se quiser, exportar
# corr_pairs_filtradas.to_csv("maiores_correlacoes.csv", index=False)

# %%
df_os_pca = df_reclame_numerico.copy()  

# %%

# removendo colunas constantes
variancia = df_os_pca.var()
colunas_variancia_zero = variancia[variancia == 0].index
if len(colunas_variancia_zero) > 0:
    print(f"⚠️ Removendo colunas constantes: {colunas_variancia_zero.tolist()}")
    df_os_pca = df_os_pca.drop(columns=colunas_variancia_zero)

# apenas numéricas
df_os_pca = df_os_pca.select_dtypes(include=[np.number])

# garantir sem NaN
df_os_pca = df_os_pca.fillna(0)


#%% Teste de Esfericidade de Bartlett
#não vou rodar devido muitas variáveis dispesas com dummy, seria interessante rodar o pca nas variaveis constantes, porem como são poucas, não vale a pena rodar.
# bartlett, p_value = calculate_bartlett_sphericity(df_os_pca)

# print(f'Qui² Bartlett: {round(bartlett, 2)}')
# print(f'p-valor: {round(p_value, 4)}')

# %%

# 1️⃣ Variáveis contínuas
variaveis_continuas = [
    'vlr_pedido', 'vlr_mercadoria', 'qtd_pedido', 'qtd_peca', 
    'qtd_adicional', 'vlr_unit_adicional', 'dias_ate_abertura',
    'tempo_resolucao', 'idade_produto'
]

# garantir que existam
variaveis_continuas = [v for v in variaveis_continuas if v in df_reclame_numerico.columns]

print(f"\n✅ Variáveis contínuas selecionadas: {variaveis_continuas}")

#%% 2️⃣ padronizar
scaler = StandardScaler()
df_continuas_scaled = pd.DataFrame(
    scaler.fit_transform(df_reclame_numerico[variaveis_continuas]),
    columns=variaveis_continuas,
    index=df_reclame_numerico.index
)

#%% 3️⃣ concatenar as dummies
# (pegando todas as colunas binárias do dataframe)
colunas_dummies = df_reclame_numerico.drop(columns=variaveis_continuas).columns
df_final = pd.concat([df_continuas_scaled, df_reclame_numerico[colunas_dummies]], axis=1)

print(f"\n✅ Dataset final para Isolation Forest tem shape: {df_final.shape}")

#%% 4️⃣ Isolation Forest
iso = IsolationForest(
    n_estimators=100,
    contamination=0.02,   # ajuste a % de anomalias esperada
    random_state=42
)

iso.fit(df_final)

#%% pontuações
scores = iso.decision_function(df_final)
outliers = iso.predict(df_final)

# adiciona ao dataframe
df_final['anomaly_score'] = scores
df_final['is_outlier'] = (outliers == -1).astype(int)

print("\n✅ Isolation Forest concluído.")
print(df_final[['anomaly_score', 'is_outlier']].head(20))

#%% 5️⃣ proporção de anomalias
n_outliers = df_final['is_outlier'].sum()
print(f"\n⚠️ Foram detectados {n_outliers} outliers em {len(df_final)} registros ({round(100 * n_outliers/len(df_final),2)}%)")

# %%

outliers_df = df_final[df_final['is_outlier'] == 1]
print(outliers_df.head())

# %%
# analisar a média/mediana das variáveis para os outliers
print(outliers_df.describe())

# %%
for col in variaveis_continuas:
    plt.figure(figsize=(8,4))
    sns.kdeplot(df_final[df_final['is_outlier']==0][col], label='Normal', fill=True)
    sns.kdeplot(df_final[df_final['is_outlier']==1][col], label='Outlier', fill=True)
    plt.title(f"Distribuição da variável {col}")
    plt.legend()
    plt.show()
# %%
outliers_df = df_final[df_final['is_outlier'] == 1]

# %%
# calcula a frequência de 1s em cada dummy
impacto_dummies = []

for dummy in colunas_dummies:
    pct_outliers = outliers_df[dummy].mean() * 100
    pct_normais  = df_final[df_final['is_outlier']==0][dummy].mean() * 100
    diff = pct_outliers - pct_normais
    impacto_dummies.append( (dummy, pct_outliers, pct_normais, diff) )

# transforma em dataframe
df_impacto = pd.DataFrame(impacto_dummies, columns=[
    'dummy', 'pct_outliers', 'pct_normais', 'diferenca'
])

# ordena pelo impacto absoluto (maior diferença)
df_impacto = df_impacto.reindex(df_impacto['diferenca'].abs().sort_values(ascending=False).index)

print("\n========== RANKING DE INFLUÊNCIA DAS DUMMIES ==========")
print(df_impacto.head(20))  # top 20, mas pode ajustar
# arredondar antes de exportar
df_impacto['pct_outliers'] = df_impacto['pct_outliers'].round(2)
df_impacto['pct_normais']  = df_impacto['pct_normais'].round(2)
df_impacto['diferenca']    = df_impacto['diferenca'].round(2)

# salvar
df_impacto.to_csv("Dummuys_maior_impacto_nos_outliers.csv", index=False)

print("✅ CSV exportado com percentuais formatados!")

# %%
# garantir que só passem as colunas originais do fit
features_treinadas = df_final.drop(columns=['anomaly_score', 'is_outlier'], errors='ignore')

# calcular o score
scores = iso.decision_function(features_treinadas)

# adicionar no dataframe
df_final['anomaly_score'] = scores

# filtrar outliers
outliers_df = df_final[df_final['is_outlier'] == 1].copy()

# apenas as colunas relevantes
outliers_com_score = outliers_df[['anomaly_score']].copy()

print(outliers_com_score.head())

# exportar
outliers_df.to_csv("outliers_com_scores.csv", index=False)

print("\n✅ CSV de outliers exportado com sucesso.")

# %%
# definindo intervalo
min_score = df_final['anomaly_score'].min()
max_score = df_final['anomaly_score'].max()

# normalizando invertido
df_final['fraude_risco_pct'] = (max_score - df_final['anomaly_score']) / (max_score - min_score) * 100

# conferindo
print(df_final[['anomaly_score', 'fraude_risco_pct']].head())

# se quiser ver os top 10 maiores riscos
print("\nTOP 10 maiores riscos detectados:")
print(df_final.sort_values('fraude_risco_pct', ascending=False)[['anomaly_score', 'fraude_risco_pct']].head(10))

# %%
