from flask import Blueprint, render_template
import pandas as pd
from includes.db_connection import get_db_connection

# Cria o blueprint para o módulo financeiro
financeiro_bp = Blueprint('financeiro', __name__, url_prefix='/financeiro')

@financeiro_bp.route('/titulos_inadimplencia')
def titulos_inadimplencia():
    engine = get_db_connection()
    query = "SELECT TOP 100 * FROM ZSAP..V_CONTAS_RECEBER_SAP"
    df = pd.read_sql(query, engine)
    
    # Converte a coluna de vencimento para datetime, tratando erros e removendo valores inválidos
    df['DTVENCIMENTO'] = pd.to_datetime(df['DTVENCIMENTO'], errors='coerce')
    df = df.dropna(subset=['DTVENCIMENTO'])
    
    hoje = pd.Timestamp.today().normalize()
    df['dias_atraso'] = (hoje - df['DTVENCIMENTO']).dt.days

    # Filtra títulos inadimplentes (exemplo)
    cond_inadimplencia = (
        (df['DTVENCIMENTO'] < hoje) &
        (df['dias_atraso'] >= 31) & (df['dias_atraso'] <= 365) &
        (df['STATUSCOMP'] != 'Compensado') &
        ((df['BLQADVERTENCIA'].isna()) | (df['BLQADVERTENCIA'] == ''))
    )
    total_titulos = len(df)
    total_inadimplentes = df[cond_inadimplencia].shape[0]
    indice_inadimplencia = (total_inadimplentes / total_titulos * 100) if total_titulos > 0 else 0
    valor_total_inadimplencia = df[cond_inadimplencia]['MONTANTE'].sum()

    aging_bins = [0, 30, 60, 90, 180, 365, df['dias_atraso'].max() + 1]
    aging_labels = ['0-30', '31-60', '61-90', '91-180', '181-365', '366+']
    df['aging_category'] = pd.cut(df['dias_atraso'], bins=aging_bins, labels=aging_labels, right=False)
    aging_counts = df['aging_category'].value_counts().sort_index()

    # Monta o dicionário com os indicadores
    indicators = {
        'indice_inadimplencia': round(indice_inadimplencia, 2),
        'valor_total_inadimplencia': valor_total_inadimplencia,
        'total_titulos': total_titulos,
        'total_inadimplentes': total_inadimplentes,
        'aging_counts': aging_counts.to_dict()
    }
    # Aqui, passamos a variável "indicators" para o template
    return render_template('financeiro_indicadores_inadimplencia.html', indicators=indicators)
