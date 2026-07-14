# Create a Slack App to Talk to AI

## Create the App

1. Go to https://api.slack.com/apps
2. Click "Create New App"
3. Select "From a Manifest"
4. Pick the LezWatch.TV Workspace
5. Enter the following manifest:

```json
{
    "display_information": {
        "name": "LezWatch AI",
        "description": "A curated guide to queer TV.",
        "background_color": "#5b2d86"
    },
    "features": {
        "bot_user": {
            "display_name": "LezWatch AI",
            "always_online": true
        }
    },
    "oauth_config": {
        "scopes": {
            "bot": [
                "app_mentions:read",
                "chat:write",
                "chat:write.public"
            ]
        }
    },
    "settings": {
        "event_subscriptions": {
            "request_url": "https://ai.ipstenu.com/slack/events",
            "bot_events": [
                "app_mention"
            ]
        },
        "org_deploy_enabled": false,
        "socket_mode_enabled": false,
        "token_rotation_enabled": false
    }
}
```

Copy the "Signing Secret" from that page and save it as `SLACK_SIGNING_SECRET` -- you'll need it in a minute.

On the left sidebar, click on OAUTH and "INSTALL TO LEZWATCH.TV"

This will come back with an oAuth token starting `xoxb-` - Save that as `SLACK_BOT_TOKEN`

## Setup Bolt on the AI server

Login to `ai.ipstenu.com`

### Build the VR Environment

We use an environment for the sync, so we're going to share that:

```Bash
/home/uptime/agents/venv/bin/pip install slack-bolt flask requests python-dotenv gunicorn
```

### Install the files

Copy the file `/scripts/slack_bot.py` to `/home/uptime/agents/bin/slack_bot.py`

Replace `SLACK_SIGNING_SECRET` and `SLACK_BOT_TOKEN` with the data you picked up earlier.

```bash
export SLACK_BOT_TOKEN="xoxb-your-token-here"
export SLACK_SIGNING_SECRET="your-signing-secret-here"
```

Now enter the bin folder:

```bash
/home/uptime/agents/venv/bin/gunicorn --bind 127.0.0.1:3000 /home/uptime/agents/bin/slack_bot:flask_app --timeout 300 --pid /tmp/slack_bot.pid --daemon
```

You'll need to do that any time you alter the files

To restart in the future, you can simply run:

`kill -HUP $(cat /tmp/slack_bot.pid)`

The HUP (Hangup) signal tells Gunicorn to perform a "graceful restart," reloading the code without dropping current connections.
