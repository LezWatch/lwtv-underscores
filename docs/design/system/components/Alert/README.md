Use to tell people something about the page or an action. Tones: `info`, `success`, `warning`, `danger`. Each starts with a bold label word (Note, Success, Warning, Error) so status never depends on color. Override it with `label`.

On light surfaces the alert is tinted; on the dark surface it is outlined, because the saturated dark-theme tints fail contrast with `ink`. The theme sets no custom alert colors of its own, so these follow the status tokens.

The consumer provides the message as children.
