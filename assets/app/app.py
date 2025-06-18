import os
import sys
import logging
from flask import Flask
from auth.market import market_bp
from views.financeiro import financeiro_bp

# Adiciona o diretório raiz do projeto ao sys.path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../")))

def create_app():
    # Define o caminho absoluto para a pasta de templates
    templates_path = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../templates"))
    app = Flask(__name__, template_folder=templates_path)

    # Configura o logging para capturar erros em um arquivo
    log_dir = os.path.dirname(os.path.abspath(__file__))
    log_file = os.path.join(log_dir, "error.log")

    logging.basicConfig(
        filename=log_file,
        level=logging.ERROR,
        format="%(asctime)s - %(levelname)s - %(message)s"
    )

    # Registra os blueprints
    app.register_blueprint(market_bp)
    app.register_blueprint(financeiro_bp)

    @app.route('/')
    def index():
        return "Hello, Flask! Ambiente Financeiro funcionando."

    return app

# Cria a aplicação Flask
app = create_app()

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
