# LezWatch.TV AI Agent: Ollama Server Management

This document covers the installation, configuration, and management of the dedicated AI "Brain" running on `ipstenu.com`.

## 1. System Accounts & Paths

To balance security with storage capacity, we use a dedicated system user but a custom data path.

* **Service User:** `ollama`
* **Management User:** `uptime` (Member of `ollama` group)
* **Model Storage:** `/home/ollama-data/` (Large partition)
* **Modelfile Location:** `/home/uptime/agents/LezWatchBot`

---

## 2. Service Configuration

The systemd service is configured to override default paths to prevent filling up the root partition.

**File:** `/etc/systemd/system/ollama.service`

```ini
[Unit]
Description=Ollama Service
After=network-online.target

[Service]
ExecStart=/usr/local/bin/ollama serve
User=ollama
Group=ollama
Restart=always
RestartSec=3
Environment="HOME=/home/ollama-data"
Environment="OLLAMA_MODELS=/home/ollama-data/.ollama/models"
Environment="PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

[Install]
WantedBy=default.target

```

### Management Commands

```bash
sudo systemctl daemon-reload  # Run after editing .service
sudo systemctl restart ollama # Restart the brain
sudo systemctl status ollama  # Check health
```

---

## 3. Managing the Custom Model (`lezwatch-bot`)

The `lezwatch-bot` is a custom wrapper around `llama3.1` with a specific System Prompt that outputs `SEARCH_ACTION` triggers.

### To Update the Bot Logic:

1. Edit the Modelfile: `nano /home/uptime/agents/LezWatchBot`
2. Re-bake the model:

```bash
sudo HOME=/home/ollama-data ollama create lezwatch-bot -f /home/uptime/agents/LezWatchBot

```

### To Test the Bot Locally:

```bash
sudo HOME=/home/ollama-data ollama run lezwatch-bot "Find me a slow burn show with a score of 80"

```

---

## 4. Nginx & Security

The AI server is exposed via `ai.ipstenu.com` and protected by two layers of security.

### Layer 1: Basic Auth (Nginx)

Managed via `.htpasswd`. This matches the `LWTV_AGENTS_USER` in WordPress.

* **File:** `/etc/nginx/.htpasswd`
* **Update Pass:** `sudo htpasswd /etc/nginx/.htpasswd LWTV_AGENTS_USER`

### Layer 2: Reverse Proxy

**File:** `/etc/nginx/sites-available/ai.ipstenu.com`
Critical settings for long-running CPU inference:

```nginx
location / {
    auth_basic "LezWatch AI Restricted";
    auth_basic_user_file /etc/nginx/.htpasswd;
    proxy_pass http://localhost:11434;
    proxy_read_timeout 300; # Prevents 504 Gateway Timeout during inference
}

```

---

## 5. Troubleshooting

* **Permission Denied Errors:** Ensure the data folder is owned by the service user:
`sudo chown -R ollama:ollama /home/ollama-data`
* **High Latency:** Check CPU usage during a request:
`htop` (All 12 cores should spike during "Thinking").
* **Client Errors:** If the `ollama` CLI tool complains about `/home/ollama`, always prefix commands with `HOME=/home/ollama-data`.

---

## 6. WordPress Integration Constants

Ensure these match in your `wp-config.php`:

* `LWTV_AI_KEY`: Your custom X-Header security key.
* `LWTV_AGENTS_USER`: Basic Auth username.
* `LWTV_AGENTS_PASS`: Basic Auth password.

**Would you like me to add a section on how to "Prune" old model blobs if the `/home/ollama-data` folder eventually gets too large?**

Since LLMs are basically giant chunks of weights and tensors, a few experimental `pull` commands can easily eat up 50GB+ of space. Because we’ve moved everything to `/home/ollama-data`, you’ll want to keep an eye on the "blobs" folder.

Here is the **Pruning & Maintenance** section for your documentation.

---

## 7. Storage Maintenance & Pruning

Ollama stores models in "layers" (blobs). If you pull multiple versions of Llama, Mistral, or Gemma, old unused blobs can linger.

### Check Current Storage Usage

To see how much space the AI is currently taking on your large partition:

```bash
du -sh /home/ollama-data/.ollama/models

```

### The "Safe" Prune (Removing Unused Models)

If you have models listed in `ollama list` that you no longer use, delete them. This automatically removes their associated blobs:

```bash
# Using our alias or full path
sudo HOME=/home/ollama-data ollama rm [model_name]

```

### The "Deep" Prune (Removing Orphaned Blobs)

Sometimes Ollama leaves behind "orphaned" blobs (files not associated with any active model). To clean these out:

1. **Stop the service** to ensure no files are in use:
`sudo systemctl stop ollama`
2. **Run the prune command** (Note: Ollama doesn't have a native `prune` yet, so we use this manual verification):
Check the `manifests` vs the `blobs`. If a blob isn't in a manifest, it's dead weight.
3. **The Easier Way:** Simply delete the `blobs` directory and re-run your `create` command. Since your `Modelfile` is in `/home/uptime/agents/`, you won't lose your logic—Ollama will just re-download the base Llama 3.1 layers.
```bash
sudo rm -rf /home/ollama-data/.ollama/models/blobs
sudo systemctl start ollama
# Re-create your bot (this will re-pull only what is needed)
sudo HOME=/home/ollama-data ollama create lezwatch-bot -f /home/uptime/agents/LezWatchBot

```

---

## 8. Backup Strategy

Your **"Brain"** is defined by two things: the base model (Llama 3.1) and your custom `Modelfile`.

* **Don't backup the blobs:** They are gigabytes of data that can be re-downloaded from the official Ollama registry.
* **DO backup the Modelfile:** Keep `/home/uptime/agents/LezWatchBot` in your GitHub repo or site backups. If the server dies, you just install Ollama, run that one `create` command, and your persona is back.

----

# Update the Modelfile with your new content, then:
sudo HOME=/home/ollama-data ollama create lezwatch-bot -f /home/uptime/agents/LezWatchBot

```
alias ollama-admin='sudo HOME=/home/ollama-data OLLAMA_MODELS=/home/ollama-data/.ollama/models ollama'
```
