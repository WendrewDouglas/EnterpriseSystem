@echo off
REM Define as variáveis de ambiente para usar o Python do ambiente virtual
set PY_PYTHON=3.11
set "PATH=C:\xampp\htdocs\intranet\forecast\venv\Scripts;C:\xampp\htdocs\intranet\forecast\venv\Lib\site-packages;%PATH%"

REM Opcional: exibe o Python usado para depuração
echo Python executável: 
"C:\xampp\htdocs\intranet\forecast\venv\Scripts\python.exe" --version

REM Muda para o diretório onde está o wsgi.py
cd /d C:\xampp\htdocs\intranet\forecast\assets\app

REM Executa o Waitress para iniciar a aplicação
"C:\xampp\htdocs\intranet\forecast\venv\Scripts\python.exe" -m waitress --listen=0.0.0.0:5000 wsgi:app
pause
