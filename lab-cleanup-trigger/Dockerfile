FROM python:3.11-slim

WORKDIR /app

COPY cleanup_trigger.py .

RUN pip install httpx

CMD ["python", "cleanup_trigger.py"]
