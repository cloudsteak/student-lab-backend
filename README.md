# Student Lab Backend

FastAPI alapú backend automatikus Azure lab környezetek létrehozásához, email értesítéssel, Auth0 hitelesítéssel, Redis-alapú TTL kezelés.

## Futás Dockerrel:

```bash
docker build -t student-lab-backend .
docker run -p 8000:8000 --env-file .env student-lab-backend
```

## API végpontok:
- `POST /start-lab`: lab indítása (Auth0 token szükséges)
- `GET /lab-status/{username}`: lab állapot lekérdezése

## Szükséges környezeti változók:
Lásd `.env.example` fájlt.


## Docker képfájl fordítása helyben:
```bash
docker buildx build --platform linux/amd64 -t ghcr.io/cloudsteak/lab-backend:latest .
```


