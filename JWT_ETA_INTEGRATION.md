# JWT Integration with ETA Login System

## ✅ Yes, JWT is Fully Integrated!

The ETA login system **fully supports JWT authentication**. Here's how it works:

## How JWT Works with ETA Login

### 1. **Token Generation After ETA Authentication**

When a user logs in via ETA (either existing user or new registration), the system automatically generates JWT tokens:

```php
// In AuthController::etaLogin()
$tokens = $this->jwtService->generateTokenPair($user, $deviceInfo);
```

### 2. **JWT Token Structure**

The JWT access token includes these custom claims:
- `user_id`: User database ID
- `eitaa_id`: ETA user ID (retrieved from user_meta if not on user model)
- `roles`: User roles array
- `is_super_admin`: Boolean flag
- `device_info`: Device information

### 3. **Response Format**

Both login and registration responses include JWT tokens:

**Existing User (Login):**
```json
{
    "login": true,
    "user": {...},
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "random64characterstring...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

**New User (Registration):**
```json
{
    "register": true,
    "user": {...},
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "random64characterstring...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

## Available JWT Endpoints

### 1. **ETA Login** (Generates JWT)
```
POST /api/auth/eta-login
```

### 2. **Refresh Token**
```
POST /api/auth/refresh
Body: { "refresh_token": "..." }
```

### 3. **Logout** (Revokes Token)
```
POST /api/auth/logout
Body: { "refresh_token": "..." }
```

### 4. **Get Authenticated User** (Uses JWT)
```
GET /api/auth/me
Headers: Authorization: Bearer {access_token}
```

## How to Use JWT Tokens

### 1. **After ETA Login**

Save the tokens from the response:
```javascript
// Example: Save tokens after login
const response = await fetch('/api/auth/eta-login', {
    method: 'POST',
    body: JSON.stringify({
        eitaa_data: "...",
        utm: "..."
    })
});

const data = await response.json();
localStorage.setItem('access_token', data.access_token);
localStorage.setItem('refresh_token', data.refresh_token);
```

### 2. **Making Authenticated Requests**

Include the access token in the Authorization header:
```javascript
fetch('/api/auth/me', {
    headers: {
        'Authorization': `Bearer ${localStorage.getItem('access_token')}`
    }
});
```

### 3. **Refreshing Tokens**

When the access token expires, use the refresh token:
```javascript
fetch('/api/auth/refresh', {
    method: 'POST',
    body: JSON.stringify({
        refresh_token: localStorage.getItem('refresh_token')
    })
});
```

## JWT Features

### ✅ **Access Token**
- Short-lived (default: 60 minutes)
- Contains user information in claims
- Used for API authentication
- Automatically blacklisted when new token is generated

### ✅ **Refresh Token**
- Long-lived (default: 14 days)
- Stored in database (hashed)
- Used to get new access tokens
- Can be revoked for logout

### ✅ **Token Claims**
- User ID for identification
- ETA ID for ETA-specific operations
- Roles for authorization
- Device info for tracking

## Security Features

1. **Token Validation**: All tokens are validated using HMAC SHA256
2. **Token Blacklisting**: Old access tokens are automatically blacklisted
3. **Refresh Token Rotation**: New access tokens on refresh, same refresh token
4. **Secure Storage**: Refresh tokens stored as SHA256 hashes in database
5. **Expiration**: Tokens automatically expire based on configuration

## Configuration

JWT settings in `.env`:
```env
JWT_SECRET=your-secret-key
JWT_TTL=60                    # Access token lifetime (minutes)
JWT_REFRESH_TTL=20160         # Refresh token lifetime (minutes)
JWT_ALGO=HS256
JWT_BLACKLIST_ENABLED=true
```

## Complete Flow

```
1. User sends ETA init data
   ↓
2. System validates ETA data
   ↓
3. System checks/creates user
   ↓
4. System generates JWT tokens (access + refresh)
   ↓
5. System returns tokens with user data
   ↓
6. Client uses access token for API requests
   ↓
7. When expired, client uses refresh token
   ↓
8. System returns new access token
```

## Example: Complete Authentication Flow

```javascript
// 1. Login with ETA
const loginResponse = await fetch('/api/auth/eta-login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        eitaa_data: eitaaInitData,
        utm: 'source=eitaa'
    })
});

const loginData = await loginResponse.json();
// loginData.access_token
// loginData.refresh_token

// 2. Use access token for API calls
const userResponse = await fetch('/api/auth/me', {
    headers: {
        'Authorization': `Bearer ${loginData.access_token}`
    }
});

// 3. Refresh when needed
const refreshResponse = await fetch('/api/auth/refresh', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        refresh_token: loginData.refresh_token
    })
});

// 4. Logout
await fetch('/api/auth/logout', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        refresh_token: loginData.refresh_token
    })
});
```

## Summary

✅ **JWT is fully integrated** with the ETA login system  
✅ **Tokens are automatically generated** after ETA authentication  
✅ **All JWT endpoints work** (refresh, logout, me)  
✅ **Secure token management** with blacklisting and expiration  
✅ **eitaa_id is included** in token claims (from user_meta)  

The system seamlessly combines ETA authentication with JWT token-based API access! 🚀


