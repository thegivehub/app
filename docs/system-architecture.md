# System Architecture - The Give Hub

## Overview

The Give Hub is a modern crowdfunding platform built with PHP backend services, MongoDB database, and blockchain integration for secure, transparent donations.

## Architecture Components

### Backend Services
- **PHP 8.2** - Core application logic
- **MongoDB** - Primary database for user data, campaigns, and transactions
- **JWT Authentication** - Stateless authentication system
- **RESTful APIs** - Comprehensive API endpoints for all operations

### Frontend
- **Vanilla JavaScript** - No framework dependencies for fast loading
- **Web Components** - Reusable UI components
- **Progressive Enhancement** - Works without JavaScript
- **Responsive Design** - Mobile-first approach

### Security Layer
- **CSRF Protection** - Token-based CSRF prevention
- **Rate Limiting** - Prevents abuse and DDoS attacks
- **Security Headers** - Comprehensive HTTP security headers
- **Input Sanitization** - All user inputs are sanitized
- **SQL Injection Prevention** - Parameterized queries and MongoDB ODM

### Blockchain Integration
- **Stellar Network** - Primary blockchain for transactions
- **Multi-Currency Support** - XLM, BTC, ETH support
- **Smart Contracts** - Campaign and milestone contracts
- **Multi-Signature Wallets** - Enhanced security for large transactions

### Performance & Monitoring
- **Caching System** - File-based caching for performance
- **Database Indexing** - Optimized database queries
- **Performance Monitoring** - Request timing and profiling
- **Error Logging** - Comprehensive error tracking

## System Flow

### User Registration & Authentication
```
1. User submits registration form
2. CSRF token validation
3. Input sanitization and validation
4. Password hashing (bcrypt)
5. MongoDB user creation
6. Email verification sent
7. JWT token generation on login
```

### Campaign Creation
```
1. User authentication check
2. Campaign data validation
3. Image upload and processing
4. MongoDB campaign storage
5. Blockchain wallet creation
6. Campaign activation
7. Search index update
```

### Donation Process
```
1. Donor selects campaign
2. Payment method selection
3. KYC/AML verification (if required)
4. Blockchain transaction creation
5. Transaction monitoring
6. Funds escrow management
7. Milestone-based fund release
```

### KYC/AML Processing
```
1. Document upload and validation
2. Liveness detection verification
3. Risk scoring calculation
4. Compliance reporting
5. Admin review process
6. Automated decision making
7. Audit trail creation
```

## Database Schema

### Users Collection
```javascript
{
  _id: ObjectId,
  username: String,
  email: String,
  status: String, // 'active', 'pending', 'suspended'
  personalInfo: {
    firstName: String,
    lastName: String,
    email: String,
    language: String
  },
  auth: {
    passwordHash: String,
    verified: Boolean,
    lastLogin: Date,
    refreshToken: String
  },
  profile: {
    avatar: String,
    bio: String,
    preferences: Object
  },
  roles: [String], // ['user', 'admin', 'moderator']
  createdAt: Date,
  updatedAt: Date
}
```

### Campaigns Collection
```javascript
{
  _id: ObjectId,
  title: String,
  description: String,
  creator: ObjectId, // User ID
  target: Number,
  raised: Number,
  status: String, // 'active', 'completed', 'cancelled'
  category: String,
  images: [String],
  milestones: [{
    title: String,
    description: String,
    amount: Number,
    completed: Boolean,
    completedAt: Date
  }],
  blockchain: {
    walletAddress: String,
    network: String,
    contractAddress: String
  },
  createdAt: Date,
  updatedAt: Date
}
```

### Blockchain Transactions Collection
```javascript
{
  _id: ObjectId,
  hash: String,
  from: String,
  to: String,
  amount: Number,
  currency: String,
  network: String,
  status: String, // 'pending', 'confirmed', 'failed'
  campaign: ObjectId,
  donor: ObjectId,
  fees: {
    network: Number,
    platform: Number
  },
  createdAt: Date,
  confirmedAt: Date
}
```

## API Architecture

### RESTful Endpoints
- **Base URL**: `/api.php/`
- **Authentication**: JWT Bearer tokens
- **CSRF Protection**: Required for state-changing operations
- **Rate Limiting**: 100 requests per minute per IP
- **Response Format**: JSON with consistent structure

### Endpoint Patterns
```
GET    /api.php/Resource          - List resources
GET    /api.php/Resource?id=123   - Get specific resource
POST   /api.php/Resource          - Create resource
PUT    /api.php/Resource?id=123   - Update resource
DELETE /api.php/Resource?id=123   - Delete resource
```

### Special Endpoints
- **Authentication**: `/api/auth/login`, `/api/admin/login`
- **KYC Processing**: `/kyc-api.php`
- **Blockchain**: `/blockchain-transaction-api.php`
- **Admin APIs**: `/api/admin/*`

## Security Architecture

### Defense in Depth
1. **Network Level**: HTTPS only, security headers
2. **Application Level**: Input validation, CSRF protection
3. **Database Level**: Parameterized queries, access control
4. **Business Logic**: Rate limiting, authentication checks
5. **Data Level**: Encryption at rest, PII protection

### Authentication Flow
```
1. User provides credentials
2. CSRF token validation
3. Credential verification against MongoDB
4. JWT token generation with expiration
5. Token storage in HTTP-only cookie (optional)
6. Token validation on subsequent requests
7. Refresh token rotation for security
```

### Authorization Model
- **Role-Based Access Control (RBAC)**
- **Resource-Level Permissions**
- **Admin Privilege Separation**
- **Audit Logging for All Actions**

## Blockchain Architecture

### Stellar Integration
- **Network**: Testnet for development, Mainnet for production
- **Asset Support**: Native XLM and custom tokens
- **Transaction Types**: Payment, multi-signature, escrow
- **Fee Management**: Dynamic fee calculation

### Smart Contract System
```
Campaign Contract:
- Milestone-based fund release
- Multi-signature approval
- Automatic escrow management
- Donor protection mechanisms

Verification Contract:
- Multi-party verification
- Dispute resolution
- Transparent governance
- Automated compliance
```

### Multi-Currency Support
- **Bitcoin**: Direct integration for BTC donations
- **Ethereum**: ERC-20 token support
- **Stellar**: Native XLM and issued assets
- **Fiat**: Bank integration for traditional payments

## Performance Architecture

### Caching Strategy
- **File-Based Caching**: For API responses and computed data
- **Browser Caching**: Static assets with appropriate headers
- **CDN Integration**: Ready for content delivery networks
- **Database Query Optimization**: Indexes and query analysis

### Load Testing
- **Autocannon Integration**: Automated load testing
- **Performance Monitoring**: Request timing and bottleneck identification
- **Scalability Planning**: Horizontal scaling preparation
- **Resource Monitoring**: CPU, memory, and disk usage tracking

### Database Optimization
- **Index Strategy**: Optimized indexes for common queries
- **Query Profiling**: Performance analysis and optimization
- **Connection Pooling**: Efficient database connections
- **Data Partitioning**: Ready for horizontal scaling

## Monitoring & Observability

### Logging System
- **Structured Logging**: JSON format with timestamps
- **Log Levels**: Debug, Info, Warning, Error, Critical
- **Log Rotation**: Automatic log file management
- **Security Events**: Dedicated security audit logs

### Performance Monitoring
- **Request Profiling**: Timing for all API requests
- **Database Query Analysis**: Slow query identification
- **Memory Usage Tracking**: Resource consumption monitoring
- **Error Rate Monitoring**: Application health metrics

### Health Checks
- **Database Connectivity**: MongoDB connection status
- **API Endpoint Health**: Automated endpoint testing
- **Blockchain Connectivity**: Network status monitoring
- **Security System Status**: Authentication and authorization health

## Deployment Architecture

### Environment Separation
- **Development**: Local development with hot reload
- **Staging**: Production-like environment for testing
- **Production**: High-availability production deployment

### Configuration Management
- **Environment Variables**: Sensitive configuration
- **Feature Flags**: Gradual feature rollout
- **Version Management**: Git-based versioning
- **Database Migrations**: Schema evolution management

### Backup & Recovery
- **Database Backups**: Automated MongoDB backups
- **File System Backups**: User-uploaded content
- **Configuration Backups**: System configuration snapshots
- **Disaster Recovery**: Recovery procedures and testing

## Development Workflow

### Code Quality
- **PHPUnit Testing**: Comprehensive test suite (26+ tests)
- **Code Standards**: PSR-12 coding standards
- **Static Analysis**: Code quality tools integration
- **Security Scanning**: Automated vulnerability detection

### CI/CD Pipeline
- **Git Workflow**: Feature branch development
- **Automated Testing**: Test execution on commits
- **Code Review**: Peer review requirements
- **Deployment Automation**: Automated production deployment

### Documentation
- **API Documentation**: OpenAPI specification
- **System Documentation**: Architecture and setup guides
- **Developer Resources**: Integration examples and SDKs
- **Operational Runbooks**: Deployment and maintenance procedures

## Future Enhancements

### Scalability Improvements
- **Microservices Architecture**: Service decomposition planning
- **Container Deployment**: Docker and Kubernetes preparation
- **Database Sharding**: Horizontal scaling strategy
- **API Gateway**: Centralized API management

### Feature Enhancements
- **Real-time Notifications**: WebSocket integration
- **Advanced Analytics**: Business intelligence features
- **Mobile API**: Native mobile app support
- **Third-party Integrations**: Payment processor integrations

This architecture provides a solid foundation for a scalable, secure, and maintainable crowdfunding platform with comprehensive monitoring and observability capabilities.