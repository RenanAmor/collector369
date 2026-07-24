@echo off
REM Ciclo completo de 5 em 5 minutos do Collector369 (Sprint 17):
REM coleta os 3 providers totalmente automatizados e transporta o
REM resultado para producao via FTPS. Pensado para ser chamado pelo
REM Agendador de Tarefas do Windows na maquina local do Renan (o
REM Collector369 nao roda em producao — ver docs/arquitetura).
REM
REM O comando "cycle:run" encadeia os 3 collects + o transporte num unico
REM processo PHP protegido por lock (flock em storage/cache/cycle-run.lock):
REM se uma execucao anterior ainda estiver rodando quando o Agendador
REM disparar a proxima, esta e ignorada em vez de rodar em paralelo e
REM estourar o limite de creditos/minuto da Twelve Data (correcao da
REM certificacao da Sprint 17).
REM
REM Uso manual: bin\run-5min-cycle.bat
REM Agendador de Tarefas: Acao = "Iniciar um programa"
REM   Programa: C:\Projetos\L369\collector369\bin\run-5min-cycle.bat
REM   Repetir a cada: 5 minutos

cd /d "%~dp0\.."

php bin\collector369 cycle:run
