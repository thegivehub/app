# 🌍 The Give Hub: Empowering Communities via Crowdfunding

Welcome to **The Give Hub**! This platform enables crowdfunding for social causes in underserved regions of Africa and Latin America. Built on the Stellar blockchain and leveraging Soroban smart contracts, we provide transparency, efficiency, and accountability for impactful projects. 💫

---

## 🚀 Features

- **Blockchain Integration**: Built on the Stellar blockchain for secure, transparent transactions.
- **Smart Contract Management**: Milestone-based fund releases via Soroban smart contracts.
- **High-Impact Campaigns**: Focus on critical community needs (e.g., wells, schools, electricity).
- **Global Accessibility**: Empowering donors worldwide to make a difference.
- **Secure Donations**: Robust PHP backend ensures reliability and security.
- **Milestone Timelines**: Visual timeline on campaign pages to show upcoming and completed milestones.
- **Milestone Budgets**: Allocate funds per milestone with validation against the campaign goal.

---

## 🛠️ Tech Stack

- **Backend**: PHP 8+
- **Blockchain**: Stellar with Soroban smart contracts
- **Database**: MySQL, MongoDB
- **Frontend**: HTML, CSS, and JavaScript
- **Server**: Apache/Nginx

---

## 📦 Installation

Follow these steps to set up the project on your local machine:

### Steps

### 🚀 Getting Started (Local Development)

#### 1. Install Docker and Docker Compose

Make sure you have Docker and Docker Compose installed.
You can find installation instructions here: [https://docs.docker.com/get-docker](https://docs.docker.com/get-docker)

#### 2. Create Upload Directories

Create the necessary upload directories for the project in the project directory:

```bash
mkdir -p uploads/documents
mkdir -p uploads/selfies
chmod -R 777 uploads
```

#### 3. Build and Start the Containers

```bash
sudo docker compose build
sudo docker compose up
```

#### 4. Manually Create a User

Because the registration flow requires email verification (which won't work in local development), you’ll need to manually insert a test user into the database.

**Step A – Generate a Password Hash**

Open a PHP interactive shell:

```bash
php -a
```

Then run:

```php
echo password_hash('test1234', PASSWORD_DEFAULT);
```

Copy the resulting hash string.

**Step B – Insert a User into MongoDB**

Use the hash you copied in place of `$HASH` below:

```bash
sudo docker exec -it app-mongo-1 mongosh givehub --eval '
  db.users.insertOne({
    email: "test@example.com",
    username: "test",
    auth: {
      passwordHash: "$HASH"
    }
  });
'
```

> **Note:** Replace `$HASH` with your actual hash string. Be sure to keep the quotes around it.

#### 5. Access the Application

Visit [http://localhost:4420](http://localhost:4420) in your browser.

---

## 🤝 Contributing

We welcome contributions! Follow these steps to get involved:

1. Fork the repository.
2. Create a new branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. Commit your changes:
   ```bash
   git commit -m "Add your message here"
   ```
4. Push to your branch:
   ```bash
   git push origin feature/your-feature-name
   ```
5. Open a Pull Request.

---

## 🌟 Roadmap

### Upcoming Features

- 📱 Mobile-first design for better accessibility.
- 🌐 Multilingual support for wider reach.
- 📊 Analytics dashboard for campaign performance insights.
- 🔒 Advanced security features.

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

## 🙌 Acknowledgments

A huge thanks to all contributors and supporters of **The Give Hub**. Together, we are making a meaningful impact! 🌟

---

## 📧 Contact

For support, suggestions, or collaboration, feel free to reach out:

- Email: [support@thegivehub.com](mailto:support@thegivehub.com)
- Website: [thegivehub.com](https://thegivehub.com)
- Blog: [blog.thegivehub.com](https://blog.thegivehub.com)

---

_Changing lives, one project at a time. ❤️_

## Changelog Management

The project maintains a detailed changelog following the [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format. The changelog tracks all significant changes to the project, including new features, changes, fixes, and more.

### Viewing the Changelog

The changelog is available in the [CHANGELOG.md](CHANGELOG.md) file at the root of the repository.

### Updating the Changelog

There are two ways to update the changelog:

1. **Manual Updates**: You can edit the CHANGELOG.md file directly, adding your changes to the appropriate section under [Unreleased].

2. **Automated Updates**: Use the provided script to automatically generate changelog entries from git commits:

```bash
# Update the [Unreleased] section with all new commits
php tools/update-changelog.php

# Update with commits since a specific date
php tools/update-changelog.php --since="2025-01-01"

# Create a new version from the [Unreleased] section
php tools/update-changelog.php --version="1.1.0"
```

### Changelog Format

The changelog follows this structure:

- **[Unreleased]**: Changes that will be included in the next release
- **[Version] - Date**: Each released version with its release date
  - **Added**: New features
  - **Changed**: Changes to existing functionality
  - **Deprecated**: Features that will be removed in upcoming releases
  - **Removed**: Features that were removed
  - **Fixed**: Bug fixes
  - **Security**: Security fixes

### Best Practices

- Keep entries concise and descriptive
- Include commit hashes where relevant
- Focus on user-facing changes rather than internal code changes
- Group related changes together
- Update the changelog as part of your development process, not just before releases

