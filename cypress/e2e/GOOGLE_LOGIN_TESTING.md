# Google Login Testing Guide

This document explains how to test the Google OAuth login workflow in The Give Hub application.

## Overview

Google OAuth authentication involves multiple steps and cross-origin redirects, which presents unique challenges for end-to-end testing. This guide covers various approaches and best practices.

## Testing Approaches

### 1. **UI Component Testing** (Implemented)

Tests the presence and functionality of the Google login button without actually executing the OAuth flow.

**What it tests:**
- Google login button is visible
- Button has correct styling and content
- Clicking the button initiates the OAuth flow (redirects to auth.php)

**Advantages:**
- Fast and reliable
- No external dependencies
- Works in CI/CD pipelines

**Limitations:**
- Doesn't test the actual OAuth flow
- Doesn't verify Google's authentication

**Example:**
```javascript
it('should display the Google login button', () => {
  cy.visit('/login.html');
  cy.get('#googleLogin')
    .should('be.visible')
    .should('contain', 'Sign in with Google');
});
```

### 2. **OAuth Flow Mocking** (Implemented)

Intercepts the OAuth requests and simulates successful responses without contacting Google.

**What it tests:**
- OAuth callback handling
- Token storage and session management
- Post-login redirects
- Error handling for failed OAuth

**Advantages:**
- Fast execution
- No need for real Google credentials
- Complete control over test scenarios (success, failure, edge cases)
- Works offline

**Limitations:**
- Not testing the real Google OAuth service
- Potential discrepancies between mock and real behavior

**Example:**
```javascript
it('should handle successful OAuth callback', () => {
  const mockCode = 'mock_authorization_code';
  const mockState = 'mock_state';

  cy.intercept('GET', '/auth.php*', {
    statusCode: 302,
    headers: { 'Location': '/index.html' }
  });

  cy.visit(`/auth.php?code=${mockCode}&state=${mockState}`);
  cy.url().should('include', '/index.html');
});
```

### 3. **Real OAuth Testing with Test Account** (Recommended for Staging)

Uses a real Google test account to perform actual OAuth authentication.

**What it tests:**
- Complete end-to-end OAuth flow
- Real Google authentication
- All edge cases and error scenarios

**Setup Required:**
1. Create a Google test account specifically for testing
2. Store credentials securely (NOT in code repository)
3. Use Cypress environment variables

**Advantages:**
- Tests the real OAuth flow
- Catches integration issues
- Most realistic testing scenario

**Limitations:**
- Slower execution
- Requires network connectivity
- Google may rate-limit or require CAPTCHA
- Credentials management complexity

**Example:**
```javascript
// In cypress.config.js
env: {
  GOOGLE_TEST_EMAIL: process.env.GOOGLE_TEST_EMAIL,
  GOOGLE_TEST_PASSWORD: process.env.GOOGLE_TEST_PASSWORD
}

// In test
it('should login with real Google account', () => {
  cy.visit('/login.html');
  cy.get('#googleLogin').click();

  // Handle Google's OAuth page (cross-origin)
  cy.origin('https://accounts.google.com', () => {
    cy.get('input[type="email"]').type(Cypress.env('GOOGLE_TEST_EMAIL'));
    cy.get('#identifierNext').click();
    cy.get('input[type="password"]').type(Cypress.env('GOOGLE_TEST_PASSWORD'));
    cy.get('#passwordNext').click();
  });

  // Back to app origin
  cy.url().should('include', '/index.html');
  cy.window().then((win) => {
    expect(win.sessionStorage.getItem('user')).to.not.be.null;
  });
});
```

### 4. **Hybrid Approach** (Best Practice)

Combine multiple approaches for comprehensive testing:

**Component Tests (Fast, Run Always):**
- UI presence and accessibility
- Button functionality
- Initial redirect to auth.php

**Mocked Integration Tests (Run on Every Commit):**
- OAuth callback handling
- Token management
- Error scenarios
- Session persistence

**Real OAuth Tests (Run on Staging Deployment):**
- Complete E2E flow with real Google account
- Integration verification
- Smoke tests before production

## Implementation Checklist

### Current Implementation Status

✅ **Completed:**
- [x] Google login button UI test
- [x] OAuth redirect initiation test
- [x] Mocked OAuth callback test
- [x] Error handling tests (error state, invalid state)
- [x] Session persistence test
- [x] Button accessibility test

⚠️ **Pending (Requires Configuration):**
- [ ] Real OAuth test with test account credentials
- [ ] Cross-origin OAuth flow handling with cy.origin()
- [ ] Token refresh testing
- [ ] Multi-tab session synchronization

## Running the Tests

### Run all Google login tests:
```bash
npx cypress run --spec cypress/e2e/google_login.cy.js
```

### Run in headed mode (see the browser):
```bash
npx cypress open
# Then select google_login.cy.js from the UI
```

### Run only UI tests (fast):
```bash
npx cypress run --spec cypress/e2e/google_login.cy.js --grep "should display"
```

### Run integration tests (with mocking):
```bash
npx cypress run --spec cypress/e2e/google_login.cy.js --grep "@integration"
```

## Security Considerations

### DO NOT:
❌ Commit Google OAuth credentials to the repository
❌ Store real user credentials in test files
❌ Use production OAuth credentials in tests
❌ Disable CSRF protection for testing

### DO:
✅ Use environment variables for test credentials
✅ Create dedicated test Google accounts
✅ Rotate test account credentials regularly
✅ Use separate OAuth clients for testing vs. production
✅ Store credentials in secure CI/CD secrets management

## Debugging OAuth Issues

### Common Issues and Solutions

**Issue: Cross-origin error when redirecting to Google**
```
CypressError: The command was expected to run against origin `https://app.thegivehub.com`
but the application is at origin `https://accounts.google.com`
```

**Solution:** Use `cy.origin()` to handle cross-origin commands:
```javascript
cy.origin('https://accounts.google.com', () => {
  // Commands for Google's domain
});
```

**Issue: OAuth state mismatch**
**Solution:** Ensure session state is properly managed and consistent across redirects.

**Issue: Token not stored after OAuth**
**Solution:** Verify that auth.php correctly stores tokens in session/localStorage and redirects properly.

## Best Practices

1. **Test at Multiple Levels:**
   - Unit: Button component
   - Integration: OAuth flow with mocks
   - E2E: Real OAuth on staging

2. **Use Appropriate Timeouts:**
   - OAuth flows can take longer than typical page loads
   - Set reasonable timeouts (10-30 seconds)

3. **Capture Artifacts:**
   - Save OAuth flow logs
   - Capture screenshots on failure
   - Record video of OAuth flow

4. **Tag Tests Appropriately:**
   - `@fast` - Quick UI tests
   - `@integration` - Mocked OAuth tests
   - `@e2e` - Real OAuth tests
   - `@smoke` - Critical path tests

5. **CI/CD Strategy:**
   - Run fast tests on every commit
   - Run integration tests on PR
   - Run E2E tests on staging deployment
   - Run smoke tests before production release

## Resources

- [Cypress Cross-Origin Testing](https://docs.cypress.io/api/commands/origin)
- [Google OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Cypress Best Practices](https://docs.cypress.io/guides/references/best-practices)
- [Testing OAuth Flows](https://www.cypress.io/blog/2021/03/25/oauth-testing-with-cypress/)

## Support

For issues with Google OAuth testing:
1. Check the test output and screenshots in `cypress/screenshots/`
2. Review the video recording in `cypress/videos/`
3. Check OAuth flow logs in `google-auth.log`
4. Consult this guide for common issues
