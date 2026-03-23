# LezWatch.TV AI Agent: CLI Chat Interface (`agent-chat.py`)

The `agent-chat.py` script provides a local command-line interface for interacting with the LezWatchTV AI bot. It uses a hybrid approach: leveraging a Large Language Model (LLM) for intent extraction and presentation, while using local JSON data for accurate search results.

## 1. Prerequisites

Before running the script, ensure you have the following installed and configured:

*   **Python 3.x**: The core runtime.
*   **Ollama**: The local LLM runner.
*   **`lezwatch-bot` Model**: A custom-baked model in Ollama with the appropriate system prompt.
    *   To verify, run: `ollama run lezwatch-bot "Are you ready?"`
*   **Local Catalogs**: The script requires two JSON files in the `docs/agents/data/` directory:
    *   `ai-catalog-shows.json`: Full database of shows and metadata.
    *   `ai-catalog-characters.json`: Full database of characters and metadata.
    *   *Note: These are generated using the `sync-ai.py` script.*

## 2. Quick Start

1.  **Navigate to the scripts directory**:
    ```bash
    cd docs/agents/scripts/
    ```

2.  **Run the script**:
    ```bash
    python3 agent-chat.py
    ```

3.  **Enter a query**:
    When prompted with `Ask the LezWatch AI:`, type your query in natural language.
    *   *Example: "Find me some Canadian mystery shows with a high score"*

## 3. How It Works

The script operates in a two-pass "Curator" workflow to ensure both accuracy and a conversational tone:

### Phase 1: Intent Extraction
The AI identifies the user's search criteria (e.g., genre, trope, country, score) and converts them into a structured format.

### Phase 2: Local Search
The script uses the extracted filters to query the local JSON catalogs. This bypasses LLM hallucinations and ensures that every recommended show or character actually exists in our database.

### Phase 3: Presentation
The script performs two final AI calls:
1.  **Curator's Note**: Generates a custom, one-sentence introduction based on the results found.
2.  **Result Formatting**: Generates a 2-sentence recommendation for each match, using only the verified data from the catalog.

## 4. Supported Search Parameters

The AI is trained to extract the following keys from your natural language queries:

| Key | Values | Description |
| :--- | :--- | :--- |
| `type` | `shows` or `characters` | The primary search target. |
| `country` | `usa`, `canada`, `uk`, `eu`, etc. | Geographic origin of the show. |
| `genre` | `mystery`, `sci-fi`, `comedy`, etc. | Primary genre classification. |
| `trope` | `slow-burn`, `coming-out`, etc. | Narrative elements or clichés. |
| `score` | `0-100` | The minimum LezWatch score required. |
| `limit` | `1-20` | The number of results to return (default is 3). |
| `worthit` | `yes`, `no`, `meh` | Filter by the curator's "Worth It" rating. |
| `station` | `netflix`, `hbo`, `bbc`, etc. | The airing network or streaming service. |

## 5. Privacy & Safety Filters

The agent includes a **Privacy Short-Circuit** regarding real-world actors:
*   **Blocked**: General queries about actors' personal lives, dating history, or safety.
*   **Allowed**: Representation queries (e.g., "played by a queer actor", "is the actor queer IRL?") as these are handled using metadata in the character catalog.

## 6. Troubleshooting

### "Error calling Ollama"
Ensure the Ollama service is running (`sudo systemctl status ollama` on Linux) and that you have the `lezwatch-bot` model installed.

### "I don't have a record of any shows like that"
This usually means the filters were too restrictive. Try broadening your query (e.g., "Canadian mystery" instead of "Canadian mystery with a score of 95").

### Catalogs not found
If the script can't find the `.json` files in `data/`, run the sync script first:
```bash
python3 sync-ai.py prod all
```

## 7. Internal Dependencies

The script relies on two modular helper files in the same directory:
*   `agent_chat_shows.py`: Handles show-specific filtering and formatting logic.
*   `agent_chat_characters.py`: Handles character-specific filtering and formatting logic.
