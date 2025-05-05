# The Give Hub Wallet Escrow System

## Overview

The Give Hub platform utilizes a sophisticated blockchain-based escrow system to manage donations and campaign funds securely and transparently. Every user and campaign on the platform has a corresponding Stellar blockchain wallet that serves as the foundation for all financial transactions. This document explains how the wallet escrow system works, the role it plays in the donation process, and how milestone-based funding operates through smart contracts.

## Wallet Architecture

### User and Campaign Wallets

1. **Individual Blockchain Identities**: Every user and campaign within The Give Hub has its own dedicated Stellar address/wallet
2. **Server-Side Key Management**: The platform maintains both public and private keys for all wallets
3. **Escrow by Design**: These wallets function as escrow accounts, allowing the platform to manage funds transparently while maintaining control

```
┌─────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│                 │     │                  │     │                  │
│  Donor Wallet   │────▶│  Platform Escrow │────▶│ Campaign Wallet  │
│  (User Wallet)  │     │     Accounts     │     │                  │
│                 │     │                  │     │                  │
└─────────────────┘     └──────────────────┘     └──────────────────┘
```

### Key Features

- **Isolated Accounts**: Each wallet is an isolated Stellar account with its own balance and transaction history
- **Transparent Verification**: All transactions are recorded on the Stellar blockchain for transparent verification
- **Multi-Currency Support**: Native support for Stellar XLM, with additional support for Ethereum and Bitcoin
- **Custom Transaction Metadata**: Each transaction includes metadata for reporting and analysis

## Donation Flow Using Wallet Escrow

When a donation occurs, the funds follow a controlled path through the platform's wallet escrow system:

1. **Donor Initiates Payment**:
   - Donor sends funds to a platform-controlled donation address
   - For cryptocurrency donations, this is a direct blockchain transaction
   - For traditional payment methods, the equivalent cryptocurrency amount is allocated from platform reserves

2. **Platform Processing**:
   - Funds arrive in a platform-controlled escrow wallet
   - Transaction is verified on the blockchain
   - The donation is recorded in the database with a 'pending' status

3. **Campaign Allocation**:
   - For standard donations, funds are transferred to the campaign's wallet
   - For milestone-based campaigns, funds are held in specialized escrow wallets
   - Transaction metadata links the donation to specific campaigns and donors

4. **Verification & Confirmation**:
   - All transactions are verified on the Stellar blockchain
   - Database records are updated with transaction hashes and confirmations
   - Donors receive confirmation once funds are allocated

### Sequence Diagram for Standard Donation

```
┌──────┐          ┌──────────┐          ┌──────────┐          ┌────────┐
│Donor │          │Platform  │          │Blockchain│          │Campaign│
│Wallet│          │Escrow    │          │Network   │          │Wallet  │
└──┬───┘          └────┬─────┘          └────┬─────┘          └───┬────┘
   │                   │                     │                    │
   │ Initiate Donation │                     │                    │
   │──────────────────▶│                     │                    │
   │                   │                     │                    │
   │                   │ Submit Transaction  │                    │
   │                   │────────────────────▶│                    │
   │                   │                     │                    │
   │                   │ Transaction Confirmed                    │
   │                   │◀────────────────────│                    │
   │                   │                     │                    │
   │                   │ Transfer to Campaign│                    │
   │                   │────────────────────▶│                    │
   │                   │                     │ Funds Received     │
   │                   │                     │───────────────────▶│
   │                   │                     │                    │
   │ Donation Confirmed│                     │                    │
   │◀──────────────────│                     │                    │
   │                   │                     │                    │
```

## Milestone-Based Escrow System

For campaigns with specific milestones, The Give Hub provides a more sophisticated escrow mechanism:

### How Milestone Escrow Works

1. **Escrow Account Creation**:
   - When a milestone-based campaign is created, a specialized escrow wallet is generated
   - This wallet has additional controls tied to milestone completion

2. **Donation Allocation**:
   - Donors contribute to the campaign as normal
   - Funds are held in the milestone escrow wallet rather than being immediately available

3. **Milestone Verification**:
   - Campaign organizers submit evidence of milestone completion
   - Verification may happen through various methods:
     - Administrative review by platform moderators
     - Community voting/consensus
     - Automated verification by oracles (external data sources)

4. **Smart Contract Release**:
   - Upon milestone verification, a Soroban smart contract (Stellar's smart contract platform) executes
   - The smart contract releases the appropriate portion of funds from the escrow
   - Funds are transferred to the campaign's operational wallet

5. **Transparent Record-Keeping**:
   - All milestone completions and fund releases are recorded on the blockchain
   - Donors can verify milestone progress and fund usage

### Sequence Diagram for Milestone-Based Funding

```
┌──────┐        ┌──────────┐        ┌──────────┐        ┌────────┐        ┌──────────┐
│Donor │        │Milestone │        │Smart     │        │Campaign│        │Verification│
│Wallet│        │Escrow    │        │Contract  │        │Wallet  │        │Authority  │
└──┬───┘        └────┬─────┘        └────┬─────┘        └───┬────┘        └─────┬─────┘
   │                 │                   │                  │                   │
   │ Donate to       │                   │                  │                   │
   │ Milestone       │                   │                  │                   │
   │─────────────────▶                   │                  │                   │
   │                 │                   │                  │                   │
   │                 │ Funds Held in     │                  │                   │
   │                 │ Escrow            │                  │                   │
   │                 │                   │                  │                   │
   │                 │                   │                  │ Submit Milestone  │
   │                 │                   │                  │ Completion Evidence
   │                 │                   │                  │───────────────────▶
   │                 │                   │                  │                   │
   │                 │                   │                  │                   │
   │                 │                   │                  │                   │ Verify
   │                 │                   │                  │                   │ Milestone
   │                 │                   │                  │                   │
   │                 │                   │ Milestone        │                   │
   │                 │                   │ Verified         │                   │
   │                 │                   │◀──────────────────────────────────────
   │                 │                   │                  │                   │
   │                 │ Release Funds     │                  │                   │
   │                 │◀──────────────────│                  │                   │
   │                 │                   │                  │                   │
   │                 │ Transfer Released │                  │                   │
   │                 │ Funds             │                  │                   │
   │                 │─────────────────────────────────────▶│                   │
   │                 │                   │                  │                   │
   │                 │                   │                  │                   │
```

## Soroban Smart Contracts for Milestone Verification

The Give Hub is implementing Soroban smart contracts to enhance the security and transparency of milestone-based funding. While not fully implemented yet, the system design includes:

### Smart Contract Architecture

1. **Milestone Contract**: A Soroban contract deployed for each milestone-based campaign that contains:
   - Milestone descriptions and funding allocations
   - Verification criteria and required evidence
   - Release conditions and authorizations

2. **Verification Mechanisms**:
   - Multi-signature verification requiring approvals from different parties
   - Time-locked releases that require both time passage and verification
   - Conditional releases based on external data triggers

3. **Automated Security Features**:
   - Refund capabilities if milestones aren't completed within specified timeframes
   - Partial release options for partially completed milestones
   - Dispute resolution mechanisms

### Smart Contract Functions

```javascript
// Example Soroban contract function for milestone release (simplified)
function releaseMilestoneFunds(milestoneId, verificationProof) {
    // Check milestone exists
    const milestone = this.storage.get(`milestone:${milestoneId}`);
    if (!milestone) {
        throw new Error('Milestone not found');
    }
    
    // Verify authorization
    if (!this.isAuthorized(verificationProof)) {
        throw new Error('Unauthorized verification attempt');
    }
    
    // Check milestone status
    if (milestone.status !== 'PENDING') {
        throw new Error('Invalid milestone status');
    }
    
    // Update milestone status
    milestone.status = 'COMPLETED';
    milestone.completedDate = this.ledger.timestamp;
    milestone.verificationProof = verificationProof;
    this.storage.set(`milestone:${milestoneId}`, milestone);
    
    // Release funds
    const amount = milestone.amount;
    const campaignAddress = this.storage.get('campaignAddress');
    this.token.transfer(this.contract.id, campaignAddress, amount);
    
    // Emit event
    this.events.milestoneCompleted(milestoneId, campaignAddress, amount);
    
    return {
        success: true,
        milestoneId: milestoneId,
        amount: amount,
        recipient: campaignAddress
    };
}
```

## Wallet Management

### Wallet Creation and Maintenance

1. **Automated Creation**:
   - User wallets are created upon registration
   - Campaign wallets are created when a new campaign is created
   - Milestone escrow wallets are created when milestone-based campaigns are created

2. **Balance Synchronization**:
   - Wallet balances are routinely synchronized with the blockchain
   - Scheduled tasks update balances for all wallets in the system
   - Updates occur daily for active wallets

3. **Key Security**:
   - Private keys are stored in encrypted form
   - Platform uses hardware security modules (HSMs) for production environments
   - Periodic audit procedures verify wallet integrity

### Multi-Wallet Management

For campaigns accepting multiple currencies, The Give Hub manages separate wallets for each supported cryptocurrency:

1. **Currency-Specific Addresses**:
   - Stellar (XLM) for main platform operations
   - Ethereum (ETH) wallets for Ethereum donations
   - Bitcoin (BTC) wallets for Bitcoin donations

2. **Unified Display**:
   - Despite multiple wallets, campaign displays show unified totals
   - Currency conversion rates are applied for consistent reporting
   - Historical exchange rates are stored for accurate accounting

## Integration with Donation Process

The wallet escrow system is deeply integrated with the donation process:

1. **Donation Button Integration**:
   - Donation buttons dynamically receive the appropriate wallet address
   - QR codes for crypto donations are generated from campaign wallet addresses
   - Transaction tracking begins immediately when donations are detected

2. **Real-Time Balance Updates**:
   - Campaign progress bars reflect actual blockchain balances
   - Recent donation lists show blockchain-verified transactions
   - Funding statistics are derived from verified wallet balances

3. **Transparent Verification**:
   - All transactions provide blockchain explorer links
   - Donors can verify their contributions on the blockchain
   - Campaign totals match verifiable blockchain records

## Technical Implementation

### Database Schema

Key collections for the wallet escrow system:

1. **wallets Collection**:
   ```json
   {
     "_id": "ObjectId()",
     "userId": "ObjectId() or null",
     "campaignId": "ObjectId() or null",
     "milestoneId": "ObjectId() or null",
     "walletType": "user|campaign|milestone|platform",
     "currency": "XLM",
     "publicKey": "GXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX",
     "secretKey": "encrypted-key-data",
     "balance": "10000.0000000",
     "status": "active",
     "createdAt": "ISODate()",
     "lastSyncedAt": "ISODate()"
   }
   ```

2. **blockchain_transactions Collection**:
   ```json
   {
     "_id": "ObjectId()",
     "txHash": "blockchain-tx-hash",
     "fromAddress": "source-wallet-address",
     "toAddress": "destination-wallet-address",
     "amount": "100.0000000",
     "currency": "XLM",
     "fee": "0.0000100",
     "memo": "Donation for Campaign XYZ",
     "status": "completed",
     "donationId": "ObjectId() or null",
     "campaignId": "ObjectId() or null",
     "milestoneId": "ObjectId() or null",
     "createdAt": "ISODate()",
     "confirmedAt": "ISODate() or null"
   }
   ```

3. **milestones Collection**:
   ```json
   {
     "_id": "ObjectId()",
     "campaignId": "ObjectId()",
     "title": "Phase 1: Project Launch",
     "description": "Initial setup and community outreach",
     "targetAmount": "5000.0000000",
     "walletId": "ObjectId()",
     "contractAddress": "Soroban-contract-address or null",
     "status": "pending|active|completed|failed",
     "verificationRequirements": ["admin_approval", "community_vote"],
     "completionEvidence": ["url-to-evidence-1", "url-to-evidence-2"],
     "verifiedBy": ["ObjectId(user-1)", "ObjectId(user-2)"],
     "verifiedAt": "ISODate() or null",
     "fundsReleasedAt": "ISODate() or null",
     "createdAt": "ISODate()"
   }
   ```

### API Endpoints

Key API endpoints for interacting with the wallet escrow system:

1. **Wallet Management**:
   - `GET /api.php/Wallets/getUserWallet?userId={userId}`: Get a user's wallet details
   - `GET /api.php/Wallets/getCampaignWallet?campaignId={campaignId}`: Get a campaign's wallet
   - `POST /api.php/Wallets/createWallet`: Create a new wallet
   - `GET /api.php/Wallets/getTransactions?walletId={walletId}`: Get transaction history

2. **Donation Processing**:
   - `POST /api.php/Donate/processCryptoDonation`: Process a new cryptocurrency donation
   - `GET /api.php/Transaction/getTransaction?txHash={txHash}`: Get details of a transaction
   - `POST /api.php/Transaction/verifyTransaction`: Manually verify a blockchain transaction

3. **Milestone Management**:
   - `POST /api.php/Transaction/createMilestoneEscrow`: Create a milestone escrow
   - `POST /api.php/Transaction/releaseMilestoneFunding`: Release funds from milestone escrow
   - `GET /api.php/Transaction/getMilestoneStatus?milestoneId={milestoneId}`: Check milestone status

## Security Considerations

The wallet escrow system implements several security measures:

1. **Private Key Protection**:
   - Keys are never exposed to users or client applications
   - All blockchain operations happen server-side
   - Keys are stored in encrypted form with HSM support

2. **Transaction Validation**:
   - All transactions require proper authorization
   - Multi-signature requirements for high-value operations
   - Rate limiting to prevent abuse

3. **Audit Trail**:
   - Complete history of all wallet operations
   - Immutable blockchain record for all transactions
   - Regular reconciliation between database and blockchain

## Future Enhancements

The wallet escrow system is being actively developed with these planned enhancements:

1. **Full Soroban Smart Contract Integration**:
   - Complete implementation of milestone verification contracts
   - Automated milestone verification via oracles
   - More complex conditional releases

2. **Enhanced Multi-Signature Support**:
   - Configurable threshold signatures for campaigns
   - Community governance for fund releases
   - Tiered release authorizations

3. **Cross-Chain Functionality**:
   - Bridge functionality between Stellar and other blockchains
   - Native support for more cryptocurrencies
   - Unified transaction view across blockchains

## Conclusion

The Give Hub wallet escrow system creates a secure, transparent infrastructure for handling donations. By maintaining dedicated wallets for every user and campaign, the platform ensures that all funds are traceable, verifiable, and properly allocated. The integration of Soroban smart contracts for milestone-based funding adds an additional layer of security and trust, allowing donors to contribute with confidence that funds will only be released when campaign goals are genuinely achieved.

The combination of blockchain technology, secure wallet management, and smart contract automation makes The Give Hub's donation system ideal for transparent philanthropic giving and impactful project funding.
