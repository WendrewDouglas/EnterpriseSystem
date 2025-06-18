import os
import sys
if '__file__' not in globals():
    __file__ = sys.argv[0]

import io
import requests
import pandas as pd
import matplotlib.pyplot as plt
from flask import Response, Blueprint
from datetime import datetime, timedelta

# Cria o blueprint para os indicadores de mercado com url_prefix '/market'
# Define explicitamente o root_path usando __file__
market_bp = Blueprint('market', __name__, url_prefix='/market', root_path=os.path.dirname(__file__))

@market_bp.route('/dolar/', strict_slashes=False)
def dolar_graph():
    print("Endpoint /market/dolar acessado")
    
    # Define o período: últimos 12 meses
    end_date = datetime.today()
    start_date = end_date - timedelta(days=365)
    start_date_str = start_date.strftime('%d/%m/%Y')
    end_date_str = end_date.strftime('%d/%m/%Y')

    # Consulta a API do Banco Central para a série do Dólar Comercial (série 1)
    api_url = f"https://api.bcb.gov.br/dados/serie/bcdata.sgs.1/dados?formato=json&dataInicial={start_date_str}&dataFinal={end_date_str}"
    response = requests.get(api_url)
    data = response.json()

    # Cria o DataFrame e processa os dados
    df = pd.DataFrame(data)
    df['data'] = pd.to_datetime(df['data'], dayfirst=True)
    df['valor'] = pd.to_numeric(df['valor'])
    df.set_index('data', inplace=True)

    # Agrega os dados semanalmente (fechamento na sexta-feira)
    weekly_df = df.resample('W-FRI').last()

    # Cria o gráfico
    plt.figure(figsize=(10, 6))
    plt.plot(weekly_df.index, weekly_df['valor'], marker='o', linestyle='-')
    plt.title("Evolução do Dólar Comercial (Fechamento Semanal)")
    plt.xlabel("Data")
    plt.ylabel("Valor (R$)")
    plt.grid(True)
    plt.tight_layout()

    buf = io.BytesIO()
    plt.savefig(buf, format='png')
    buf.seek(0)
    plt.close()
    
    image_data = buf.getvalue()
    print("Tamanho da imagem gerada:", len(image_data))
    return Response(image_data, mimetype='image/png')
