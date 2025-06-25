import os
import sqlalchemy
from dotenv import load_dotenv

# Carregar variáveis do .env
dotenv_path = os.path.join(os.path.dirname(__file__), "../.env")
load_dotenv(dotenv_path)

def get_db_connection():
    connection_string = (
        f"mssql+pyodbc://{os.getenv('DB_USERNAME')}:{os.getenv('DB_PASSWORD')}"
        f"@{os.getenv('DB_SERVER')}/{os.getenv('DB_DATABASE_DW')}"
        "?driver=ODBC+Driver+17+for+SQL+Server"
    )

    engine = sqlalchemy.create_engine(connection_string)
    return engine
