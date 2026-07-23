@echo off
REM Ciclo completo de 5 em 5 minutos do Collector369 (Sprint 17):
REM coleta os 3 providers totalmente automatizados e transporta o
REM resultado para producao via FTPS. Pensado para ser chamado pelo
REM Agendador de Tarefas do Windows na maquina local do Renan (o
REM Collector369 nao roda em producao — ver docs/arquitetura).
REM
REM Uso manual: bin\run-5min-cycle.bat
REM Agendador de Tarefas: Acao = "Iniciar um programa"
REM   Programa: C:\Projetos\L369\collector369\bin\run-5min-cycle.bat
REM   Repetir a cada: 5 minutos

cd /d "%~dp0\.."

php bin\collector369 collect:twelvedata
php bin\collector369 collect:yahoofinance
php bin\collector369 collect:sinafinance
php bin\collector369 transport:production
