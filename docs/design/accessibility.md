# Colour accessibility

Contrast rules that limit the palette. The target is WCAG AA: 4.5:1 for body text, 3:1 for large text and graphical marks, in both light and dark mode.

## Reference contrast figures

Measured against the tokens in `scss/partials/_colors.scss`:

| Foreground | Background | Ratio |
|---|---|---|
| `$lwtv-grey-muted` (#666) | white | 5.74:1 |
| `$lwtv-pink` (#cb3e85) | white | 4.60:1 |
| `$lwtv-pink-deep` (#9e2968) | white | 7.07:1 |
| `$lwtv-pink-deep` | `$lwtv-pink-light` (#eecee3) | 4.91:1 |

## Pink on a tint

`$lwtv-pink` only just passes on white, so it fails on any tinted background. Anywhere pink text sits on a pink tint, use `$lwtv-pink-deep`. The `.lwtv-tropegap--tint` cards and `.lwtv-hl-lead` follow the same light-fill, deep-text recipe with other families.

## Don't fade text with opacity

Lowering opacity on grey text quickly drops it below 3:1. The airdate calendar (`scss/addons/_calendar.scss`) shows past days in `$lwtv-grey-muted` at full opacity and carries "this is past" with the grey dot, not by fading the text.

For dark-mode stand-ins, see [dark-mode.md](dark-mode.md#dark-stand-ins).
