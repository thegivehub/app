# Changelog

All notable changes to the GiveHub project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Blockchain transaction status tracking system
  - MongoDB schema for blockchain transactions
  - API endpoints for transaction status management
  - Background job for automatic status updates
  - Detailed transaction status history
- Signature collection and management functionality
  - MongoDB schema for signatures collection
  - PHP API endpoints for signature operations
  - Frontend interface for capturing and managing signatures
  - Signature controller for database operations

## [1.0.0] - 2025-03-14

### Added
- Signature collection and management (commit: 6a27f33)
- Fee management throughout backend (commit: b7da728)
- KYC ID verification components and Jumio integration (commit: 15cd44d)

### Changed
- Updated MongoDB connection handling
- Improved error handling in API endpoints

### Fixed
- Authentication issues in document API
- Data validation in form submissions

## [0.9.0] - 2025-01-18

### Added
- MongoDB integration
- User authentication system
- Document upload and management
- Donation processing

### Changed
- Migrated from MySQL to MongoDB for improved scalability
- Redesigned user interface for better usability

## [0.8.0] - 2025-01-07

### Added
- User registration and login
- Campaign creation and management
- Volunteer management system
- Initial API endpoints

### Changed
- Updated frontend framework
- Improved mobile responsiveness

## [0.7.0] - 2024-12-03

### Added
- Initial project structure
- Basic frontend templates
- Database schema design
- Development environment setup

### Changed
- Updated documentation
- Improved build process

## Notes for Maintainers

When updating this changelog, please follow these guidelines:

1. Add new entries under the [Unreleased] section
2. When releasing a new version, move entries from [Unreleased] to a new version section
3. Include the following types of changes:
   - `Added` for new features
   - `Changed` for changes in existing functionality
   - `Deprecated` for soon-to-be removed features
   - `Removed` for now removed features
   - `Fixed` for any bug fixes
   - `Security` in case of vulnerabilities
4. Include commit hashes where relevant
5. Keep entries concise and descriptive 