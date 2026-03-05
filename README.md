# 💸 Fund Transfer API

A secure REST API for transferring funds between accounts — built for high reliability and concurrency safety.

**Stack:** PHP 8.3 · Symfony 7 · MySQL 8 · Redis 7 · Docker

---

## 🚀 Quick Start

### Prerequisites
- Docker Desktop installed and running

### Run the app
```bash
# Clone the repository
git clone https://github.com/shivamchit/fund-transfer-api.git
cd fund-transfer-api

# Start all services
docker-compose up -d --build

# Install dependencies
docker exec -it fund_transfer_php composer install

# Run database migrations
docker exec -it fund_transfer_php php bin/console doctrine:migrations:migrate --no-interaction

# Load test accounts (Alice & Bob)
docker exec -it fund_transfer_php php bin/console doctrine:fixtures:load --no-interaction

# Get test account UUIDs
docker exec -it fund_transfer_mysql mysql -u app_user -papp_pass fund_transfer -e "SELECT LOWER(CONCAT(SUBSTR(HEX(id),1,8),'-',SUBSTR(HEX(id),9,4),'-',SUBSTR(HEX(id),13,4),'-',SUBSTR(HEX(id),17,4),'-',SUBSTR(HEX(id),21))) as uuid, owner_name, balance, currency FROM accounts;" 2>/dev/null
```

App runs at: **http://localhost:8080**

---

## 📡 API Endpoints

### POST /api/v1/transfers — Transfer funds
```bash
curl -X POST http://localhost:8080/api/v1/transfers \
  -H "Content-Type: application/json" \
  -d '{
    "from_account_id": "uuid-here",
    "to_account_id":   "uuid-here",
    "amount":          "100.00",
    "currency":        "EUR",
    "idempotency_key": "unique-uuid-here"
  }'
```

Response:
```json
{
  "data": {
    "transaction_id": "...",
    "status": "completed",
    "amount": "100.00",
    "currency": "EUR"
  }
}
```

### GET /api/v1/accounts/{id}/balance — Check balance
```bash
curl http://localhost:8080/api/v1/accounts/{uuid}/balance
```

### GET /api/v1/health — Health check
```bash
curl http://localhost:8080/api/v1/health
```

---

## 🧪 Run Tests
```bash
docker exec -it fund_transfer_php php bin/phpunit --testdox
```

---

## 🏗️ Key Architecture Decisions

### Pessimistic Locking (SELECT FOR UPDATE)
Two simultaneous requests for the same account could both read the same balance and both succeed — causing an overdraft. `SELECT FOR UPDATE` locks the row so only one request proceeds at a time.

### Redis Distributed Locks
Redis locks prevent duplicate requests from even reaching the database, reducing load and providing a faster rejection path. Accounts are always locked in consistent UUID order to prevent deadlocks.

### Idempotency
Every request includes an `idempotency_key`. If the same key is sent multiple times (e.g. due to network retry), the system returns the original result without processing again. Checked in Redis first, then DB as fallback.

### DECIMAL(19,4) for Money
Never float or double — floating point cannot represent many decimal values precisely. All arithmetic uses PHP's `bcmath` extension for exact calculations.

### Full Audit Trail
Every transfer writes an `audit_log` record with balances before and after, making it easy to trace any transaction.

---

## 📁 Project Structure
```
src/
├── Controller/Api/     # API endpoints
├── Entity/             # Account, Transaction, AuditLog
├── Repository/         # Database queries
├── Service/            # TransferService, IdempotencyService
├── DTO/                # Request validation
└── Exception/          # Custom error types
```

---

## ⏱️ Time Spent
Approximately 6 hours

## 🤖 AI Tools
**Tool:** Claude (claude.ai) — used for scaffolding boilerplate, reviewing locking logic, and generating test cases.

**Prompts used:**
- "Create a secure API for transferring funds between accounts"
- "How to prevent race conditions in concurrent fund transfers using SELECT FOR UPDATE"
- "Generate integration tests for a Symfony fund transfer API covering idempotency, insufficient funds and validation"
- "How to implement Redis distributed locking in Symfony to prevent duplicate transfers"
