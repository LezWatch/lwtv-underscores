# Github Workflows

We have two workflows (in `./.github/workflows/`) to automate pushing code to staging (aka development) and production.

Each flow is very similar and contains the following steps:

1. Checkout the code
2. Confirm the workspace
3. Setup node
4. Setup composer and PHP
5. Install dependencies
6. Run lint
7. Build the code - this will regenerate CSS and update versions if needed
8. Rsync the code to the server
9. Call the symbolicons repository to push the new images

