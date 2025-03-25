FROM python:3.11-slim
WORKDIR /app
COPY pyproject.toml .
RUN pip install poetry && poetry config virtualenvs.create false && poetry install --no-root
COPY . .
CMD ["uvicorn", "lab-backend.main:app", "--host", "0.0.0.0", "--port", "8000"]
