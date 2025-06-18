@echo off
cd /d C:\xampp\htdocs\intranet\forecast\assets\app
call C:\xampp\htdocs\intranet\forecast\venv_new\Scripts\activate.bat
python wsgi.py
