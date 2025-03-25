FROM python:3.11-slim

# Install Poetry
RUN pip install poetry

# Set workdir
WORKDIR /app

# Copy project files
COPY pyproject.toml poetry.lock ./
RUN poetry config virtualenvs.create false && poetry install --no-interaction --no-root

COPY cleanup_trigger.py .

CMD ["python", "cleanup_trigger.py"]
