# Changelog

All notable changes to the GiveHub project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2025-03-20

### Added
- Added scripts to setup schemas for kyc and signature collection (commit: 208afd2)
- Added unit tests for Campaigns, Users, Donations and KycController (commit: 5cefaa5)
- Added stellar-api to composer dependencies (commit: 48f2f52)
- Added transaction status tracking + new CHANGELOG.md (commit: 3a14b5f)
- Added changelog and script to update changelog on checkin if so configured (commit: 3238e91)
- Added signature collection and management (commit: 6a27f33)
- Added fee management throughout backend (commit: b7da728)
- Addition of KYC ID verfication components and Jumio integration. (commit: 15cd44d)
- Added test script for docker mongodb (commit: b41f95b)
- Adding KYC document/face verification (commit: 53ac84a)
- Added missing packages for docker container MongoDB (commit: 221e4ad)
- Added docker setup for containerized app (commit: 9c79e45)
- Added nav wrapper for transaction system documentation for easy reading (commit: 3b73ad9)
- Added transaction system handling and documentation. docs include troubleshooting guide as well as implementation and best practices (commit: f5ac84a)
- Added profile completion indicator and class (commit: 73023f9)
- Addition of document uploading backend functionality + start of face verification system (commit: 38c1547)
- Added donation handling to api (commit: 7972669)
- Addition of address validation forms and endpoints (commit: ecdafc7)
- Added social media sharing and previews (commit: 3f2fa0b)
- Adding logo.png (commit: 3199062)
- Added user guide to nav tree (commit: 68c7958)
- Added campaign management user guide markdown and html (commit: 317dce5)
- Added campaign management functionality including a "My Campaigns" page that shows all of a users campaigns and an campaign-edit.html page that allows editing of data items (commit: 7c18951)
- Created api documentation from openapi yaml and added openapi-generator folders to gitignore (commit: e706a70)
- Added CORS headers (commit: e8944c6)
- Added volunteer section to app. Updated profile settings page to work correctly (commit: b1206b8)
- Added openapi yaml config for The Give Hub API (commit: 0c43332)
- Added specific early handlers for registration, send-verification and verify-code.  Still falls back to calling $instance->$action if the method exists, otherwise it continues on to our CRUD handling code (commit: daf2c62)
- Added img/avatars directory to gitignore (commit: 9ef51a5)
- Adding db setup script that creates indexes, etc (commit: ed7237c)
- Added developer registration for API key generation (commit: 50bc63c)
- Added robots.txt (commit: 1380a3d)
- Adding supporting files for projects / updated auth class with new mongo structure (commit: 0de06f9)
- Added Model.php and Mongo classes (commit: d323a46)
- Added mcc_code.json (commit: 3ea00d7)
- Adding account settings page (commit: b3f98dd)
- Adding sql schema for password reset process (commit: 59cd8f4)
- Added findId method to find ids (commit: 7649c96)
- Added SDG (Sustainable Development Goals) icons from the UN program (commit: 43e26f9)
- Adding donor listing page (commit: c058adb)
- Adding composer.lock to .gitignore (commit: 5eeeaa2)
- Adding so I can remove (commit: 9eab92f)
- Added autoload code to load our class files or create a class dynamically on the fly if one doesn't exist (commit: 01a6b76)
- Adding google authentication and maybe firebase? (commit: 1641e18)
- Adding general commit notes file (commit: 6f9fd96)
- Added a couple of cli tools for viewing and removing campaigns by id (commit: d0ac24c)
- Added labels to progress bar (commit: eccae68)
- Added htaccess with special settings for api endpoint (commit: ae2bd36)
- Added new placeholder campaign management pages that function, but only minimally (commit: c5e79ae)
- Added scemas, routes, init scripts and components (commit: 49f052a)
- Adding package-lock.json so I can remove it (commit: 24c0c2b)

### Changed
- Changed getInsertId to just reference the id property; removed toArray on what is already an array (commit: f491656)
- File cleanup and removal of duplicate files (commit: a6d11e3)
- Before merge with main (commit: 62559e9)
- Renamed sendJson function to sendAPIJson to avoid name conflict (commit: 381bf8a)
- Updated logo image and rearranged header (commit: 1431ec6)
- Updated authentication (commit: 900a9b9)
- Updated find tool to use latest class structure and id type (commit: 2633eb7)
- Cleaned up error handling (commit: 8800f45)
- Cleaned up error handling. Added getUserIdFromToken convenience function (commit: 52bebf6)
- Need to find all references to this file and change them to point to register.html (commit: e89df04)
- Style updates (commit: 326cd3f)
- Updated register.html to be *MUCH* more robust in terms of its error handling and ability to pick up where you left off if not verified. (commit: 8e7db3f)
- Updated README.md (commit: a78ea41)
- Updated MongoCollection.php to be more robust: (commit: af637a1)
- Development cleanup (commit: 47f5ff4)
- Updated .gitignore with misc. files (commit: 73c211b)
- Change logo, added settings link (commit: 24db02f)
- Registration updates. still needs work. (commit: 14f2e49)
- Updated the logo and header (commit: 7e9d7dc)
- Updated campaign-detail page with better layout (commit: f381dc9)
- Organizing project code, moving html pages into their own folder (commit: 2c02950)
- Updated black and white image logos (commit: 0857cda)
- Updated google auth workflow; added forgot password functionality and page (commit: fc77a5f)
- Changed "read" to "get" and aliased "read" to point to the new "get".  All because I kept calling it "get" and it did not exist (commit: 50bd1ad)
- Removing composer.lock file from repo (commit: e6f511e)
- Development cleanup (commit: a5a76a5)
- Moving files around (commit: 26ccf07)
- Update campaign detail with better user experience and flow. Still works on mobile and web (commit: 93050ed)
- Updating campaign-detail page and moving some files around. (commit: ccf2cbc)
- Mass updates for The Give Hub app (commit: 8fdf2b3)
- Clearing out false start code (commit: 3dd0e83)
- Removing package-lock.json (commit: 3540f48)
- Initial checkin (commit: 09c8a37)
- Initial commit (commit: e82a152)

### Fixed
- Fixed some links and errata (commit: 25dd67f)
- Fixed campaign creation (commit: 3c5f333)
- Fixed mongo insert issue (commit: 72322f6)
- Fixing mongodb issue in container (commit: ee0b391)
- Fixing any paths that still point to html files in the root and not /pages (commit: 24414e5)
- Fixed some issues with the registration and login flow.  Better error reporting, logging and generally more solid logic flow (commit: 9d97d6a)
- Fixed issue with results array being treated as a Mongo object (we changed it a while ago) (commit: e580a84)
- Fixed show.php which stopped working due to rearranging of lib/*.php files (commit: a4016f3)


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