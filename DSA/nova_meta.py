#%%!/usr/bin/env python
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

#%% Adicionar o caminho para o módulo de conexão usando caminho relativo
current_dir = os.path.dirname(os.path.abspath(__file__))
root_dir = os.path.abspath(os.path.join(current_dir, ".."))
includes_path = os.path.join(root_dir, "includes")
if includes_path not in sys.path:
    sys.path.append(includes_path)

from db_connection import get_db_connection
engine = get_db_connection()

# %% 2. Executar Queries e Carregar Dados
query_itens = "SELECT * FROM V_DEPARA_ITEM"
query_faturamento = """
-- Tabela Faturamento SAP
SELECT
    'SAP'                                    AS Origem,
    CAST(FT.DTFATUR AS DATE)                 AS Data_Faturamento,
    FT.ORGVENDA                              AS Empresa,
    CAST(FT.DOCFATUR AS BIGINT)              AS Doc_Faturamento,
    FT.CODCLI                                AS Cod_Cliente,
    UPPER(FT.NOMECLI)                        AS Cliente,
    UPPER(FT.CIDADE)                         AS Cidade,
    CASE WHEN FT.UF IN ('S','10', 'ASU') THEN 'EX'
         ELSE FT.UF END UF,
    ISNULL(CAST(FT.CODREP AS BIGINT),0)      AS Cod_Representante,

    CASE WHEN FT.ORGVENDA = '2000' OR FT.TPDOC IN ('ZVEC','ZVE2','ZVFC','ZFUL') THEN 'ECOMMERCE'
         WHEN FT.NOMEREP IS NULL THEN 'SEM REPRESENTANTE'
    ELSE FT.NOMEREP END Representante,

    FT.ESCRVENDA                             AS Cod_GNV,

    CASE 
        WHEN (FT.GRPCLIEN = '403' OR CD.VKGRP = '204') AND FT.DTFATUR <  '20240401' THEN '403'
        WHEN (FT.GRPCLIEN = '403' OR CD.VKGRP = '204') AND FT.DTFATUR >= '20240401' AND FT.DTFATUR < '20241001' THEN '204'
        WHEN (FT.GRPCLIEN = '403' OR CD.VKGRP = '204') AND FT.DTFATUR >= '20241001' THEN '604'
        WHEN FT.ORGVENDA = '2000' OR FT.TPDOC IN ('ZVEC','ZVE2','ZVFC','ZFUL') THEN '602'
        WHEN FT.CODCLI = '0001000003' THEN '601'
        WHEN FT.CODCLI IN ('0001014303','0001016199','0001027149',
                           '0001016845','0001016846','0001017105') THEN '501'
        WHEN FT.CODCLI IN ('0001006516','0001003443','0001016771',
                           '0001330822','0001332073','0001007290','0001003444') AND FT.DTFATUR >= '20240601' AND FT.DTFATUR < '20240801' THEN '405'
        WHEN (FT.GRPCLIEN = '901' OR CD.VKGRP = '901') AND FT.DTFATUR <  '20240801' THEN '301'
        WHEN FT.CODCLI IN ('0001001204','0001314472') THEN '101'
        WHEN CD.VKGRP IS NULL THEN FT.GRPCLIEN
    ELSE CD.VKGRP END Gerente_Cadastro,

    CASE 
        WHEN FT.GRPMAT = 'BEB' THEN 'PUR'
        WHEN CAST(FT.CODITEM AS BIGINT) = '120004217' THEN 'LCS'
    ELSE FT.GRPMAT END Linha,

    CAST(FT.CODITEM AS BIGINT)               AS Cod_item,
    FT.DESCITEM                              AS Desc_item,
    FT.QTDFATURA                             AS Quantidade,
    FT.VRLIQ                                 AS Valor_liquido,
    FT.TPDOC                                 AS Tipo_doc,
    FT.PARVW                                 AS Tipo_Cliente,
    FT.VRTOTAL                               AS Valor_Mercadoria,
    CAST(FT.NRNOTA AS BIGINT)                AS Nota_Fiscal,
    CASE WHEN FT.TPDOC = 'CBRE' THEN (FT.VRTOTAL - FT.NETFRE)
         ELSE (FT.VRTOTAL + FT.NETFRE) END V_FreteeTotal,

    CASE 
        WHEN FT.TPDOC = 'CBRE' THEN -(FT.NETWR + ISNULL(FT.VRICMS,0) + FT.VRIPI + FT.NETFRE)
    ELSE FT.NETWR + ISNULL(FT.VRICMS,0) + FT.VRIPI + FT.NETFRE END Valor_Bruto,

    FT.TAXACAMBIO,
    FT.MOEDA,
    FT.M3 * FT.QTDFATURA                    AS QtdM3,
    FT.PEDCLIENTE                           AS PedCliente,
    FT.EMPRESA                              AS Empresa_Of,
    FT.CENTRO                               AS Centro,
    FT.CONDICAO                             AS Condicao,
    FT.MOTIVOCANCEL                         AS MotCancel,
    FT.DOCNUM                               AS DocNum,
    FT.INCOTERMS                            AS IncoTerms,
    FT.MOTIVOCANCEL2                        AS MOTIVOCANCEL2,
    FT.APROVACAO                            AS APROVACAO,
    FT.STATUSDEVOLUCAO                      AS STATUSDEVOLUCAO,
    FT.DOCVENDA                             AS DOCVENDA,
    FT.MOTIVOORDEM                          AS MOTIVOORDEM,
    FT.TEXTOVBKD                            AS TEXTO,
    FT.TPCLIENTE,
    FT.GRPCLIEN,
    TV.BEZEI                                AS Descricao_Motivo_Ordem

FROM PowerBi..V_SAP_FATURAMENTO FT
LEFT JOIN ZSAP..TVAUT TV ON TV.AUGRU = FT.MOTIVOORDEM
LEFT JOIN ZSAP..V_CADAGENTE_SAP CD ON CD.KUNNR = FT.CODCLI AND CD.VKORG = FT.ORGVENDA
WHERE FT.DTFATUR >= '2024-01-01'
AND FT.DTFATUR < '2025-04-01'
AND FT.GRPCLIEN <> '602'
AND FT.GRPCLIEN <> ''
"""

df_faturamento = pd.read_sql(query_faturamento, engine)
df_itens = pd.read_sql(query_itens, engine)
#%%
df_itens.describe(include='all')

# %% Padronizar a Data_Faturamento para o dia 1º de cada mês
df_faturamento["Data_Faturamento"] = pd.to_datetime(df_faturamento["Data_Faturamento"])
df_faturamento["Data_Faturamento"] = df_faturamento["Data_Faturamento"].values.astype('datetime64[M]')

# %%
# Selecionar e renomear as colunas conforme solicitado
df_faturamento = df_faturamento[[
    "Data_Faturamento",    # data de faturamento
    "Empresa",             # Empresa
    "GRPCLIEN",            # grpcliente
    "TPCLIENTE",           # tpcliente
    "Cod_Cliente",         # código cliente
    "Cliente",             # cliente
    "Linha",               # linha
    "Cod_item",            # cod item
    "Desc_item",           # descr item
    "Quantidade",          # quantidade
    "Valor_liquido"        # valor líquido
]]

# Exibir top 10
print(df_faturamento.head(10))


# %%
# Filtrar os meses de abril a dezembro de 2024
df_abril_dezembro = df_faturamento[
    (df_faturamento["Data_Faturamento"] >= "2024-04-01") &
    (df_faturamento["Data_Faturamento"] <= "2024-12-31")
]

# Exportar para CSV
df_abril_dezembro.to_csv("faturamento_abril_a_dezembro_2024.csv", index=False)

# Exibir mensagem e preview
print("Arquivo 'faturamento_abril_a_dezembro_2024.csv' gerado com sucesso.")
print(df_abril_dezembro.head())

# %%
# Garantir tipos compatíveis
df_faturamento["Cod_item"] = df_faturamento["Cod_item"].astype(str)
df_itens["CODITEM"] = df_itens["CODITEM"].astype(str)

# Relacionar com a tabela de itens para obter o MODELO
df_faturamento = df_faturamento.merge(
    df_itens[["CODITEM", "MODELO"]],
    left_on="Cod_item",
    right_on="CODITEM",
    how="left"
)

# Adicionar coluna de ano
df_faturamento["Ano"] = df_faturamento["Data_Faturamento"].dt.year

# Filtrar apenas jan-fev-mar de 2024 e 2025
df_trim = df_faturamento[
    df_faturamento["Data_Faturamento"].dt.month.isin([1, 2, 3]) &
    df_faturamento["Ano"].isin([2024, 2025])
].copy()

# Agrupar e somar as quantidades
df_grouped = df_trim.groupby([
    "Empresa", "GRPCLIEN", "TPCLIENTE", "Linha", "MODELO", "Ano"
])["Quantidade"].sum().reset_index()

# Pivotar para comparar lado a lado
df_pivot = df_grouped.pivot_table(
    index=["Empresa", "GRPCLIEN", "TPCLIENTE", "Linha", "MODELO"],
    columns="Ano",
    values="Quantidade",
    fill_value=0
).reset_index()

# Renomear colunas
df_pivot.columns.name = None
df_pivot = df_pivot.rename(columns={2024: "Qtd_2024", 2025: "Qtd_2025"})

# Calcular variação absoluta
df_pivot["Variacao"] = df_pivot["Qtd_2025"] - df_pivot["Qtd_2024"]

# Exibir resultado
print("Comparativo de quantidade vendida - 1º trimestre 2024 vs 2025:")
print(df_pivot.head(10))

# Exportar para CSV
df_pivot.to_csv("comparativo_trimestre_2024_2025.csv", index=False)
print("\nArquivo 'comparativo_trimestre_2024_2025.csv' gerado com sucesso.")

# %%
