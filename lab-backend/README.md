# Student Lab Backend

## Overview

This project provides a backend solution for managing student labs. It offers APIs for handling students, labs, assignments, and grading.

## Features

- User authentication and authorization
- CRUD operations for students, labs, and assignments
- Assignment submission and grading
- RESTful API design
- Integration with databases

## Technology Stack

- **Language:** Node.js (TypeScript)
- **Framework:** Express.js
- **Database:** PostgreSQL
- **ORM:** Prisma
- **Authentication:** JWT

## Getting Started

1. Clone the repository.
2. Install dependencies:  
  ```bash
  npm install
  ```
3. Configure environment variables.
4. Run database migrations:  
  ```bash
  npx prisma migrate deploy
  ```
5. Start the server:  
  ```bash
  npm start
  ```

## API Documentation

See [API.md](./API.md) for detailed API endpoints and usage.

## Contributing

Contributions are welcome! Please open issues or submit pull requests.

## License

This project is licensed under the MIT License.