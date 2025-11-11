# GitHub Actions: SSH Deployment Key Setup

## Settings

Since we call workflows in other projects, go to `https://github.com/LezWatch/lwtv-underscores/settings/actions`

And make sure this is selected:

**Allow all actions and reusable workflows**
Any action or reusable workflow can be used, regardless of who authored it or where it is defined.

## A reminder on how to configure deployment secrets when the server changes.

You're probably reading this because the deployment action is failing with a `Permission denied` or `Too many authentication failures` error after a server move. The problem is almost always a mix-up between the public and private SSH keys.

## The Core Concept: Lock and Key

Think of your SSH keys as a physical lock and key.

*   **`id_rsa.pub` (Public Key / The Lock):** This is the lock. It's safe to share. You install this "lock" on the server you want to access.
*   **`id_rsa` (Private Key / The Key):** This is the secret key. **It must never be shared publicly.** You give this "key" to the program (in this case, GitHub Actions) that needs to open the lock.

## The 3-Step Process

How to grant GitHub Actions access to a new server.

### Step 1: Generate a New, Dedicated SSH Key Pair

On the LezWatch.TV Server, create a new key pair specifically for this purpose. This avoids using your personal keys.

```bash
ssh-keygen -m PEM -t rsa -b 4096 -f github_actions_deploy_key
```

* `-m PEM` is important for compatibility with older systems/actions
* `-t rsa -b 4096` is a strong encryption standard
* `-f` specifies the filename

Note: This will create two files in your current directory: github_actions_deploy_key (the private key) and github_actions_deploy_key.pub (the public key).

### Step 2: Install the "Lock" on the New Server

You need to copy the content of the public key (github_actions_deploy_key.pub) and add it as a new line to the authorized_keys file on the new server.

Copy the content of the public key file to ` ~/.ssh/authorized_keys`. It's a single, long line of text and can be done like this:

``` bash
cat ~/.ssh/id_rsa.pub >> ~/.ssh/authorized_keys
```
Crucial: Ensure the file permissions on the server are correct. SSH will refuse to connect if they are too open.

```bash
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

### Step 3: Give the "Key" to GitHub Actions
Copy the content of the **private key** (`~/.ssh/id_rsa`) and save it as a secret in your GitHub organization.

Copy the entire content of the private key file, including the `-----BEGIN RSA PRIVATE KEY-----` and `-----END RSA PRIVATE KEY-----` lines.

Navigate to your LezWatch organization's secrets page: https://github.com/organizations/LezWatch/settings/secrets/actions

Find the secret used by the workflow (e.g., LWTV_SSH_KEY) and click Update. If creating a new one, use the same name referenced in your workflow's .yml file.

Paste the complete private key into the value field and save it.
