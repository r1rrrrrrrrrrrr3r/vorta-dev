<?php
/**
 * lib/preferences.php
 *
 * Per-user UI preferences (colour theme + navigation layout).
 *
 * Validation lives here so the AJAX endpoint and any server-rendered form
 * cannot accept different values than the database ENUMs allow.
 */

const PREF_THEMES = ['light', 'dark', 'system'];
const PREF_LAYOUTS = ['navbar', 'sidebar'];

const PREF_THEME_DEFAULT = 'system';
const PREF_LAYOUT_DEFAULT = 'navbar';

function pref_valid_theme(?string $value): bool
{
    return $value !== null && in_array($value, PREF_THEMES, true);
}

function pref_valid_layout(?string $value): bool
{
    return $value !== null && in_array($value, PREF_LAYOUTS, true);
}

/**
 * Read a user's stored preferences, falling back to defaults.
 *
 * @return array{theme: string, nav_layout: string}
 */
function preferences_load(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT theme, nav_layout FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return [
        'theme' => pref_valid_theme($row['theme'] ?? null) ? $row['theme'] : PREF_THEME_DEFAULT,
        'nav_layout' => pref_valid_layout($row['nav_layout'] ?? null) ? $row['nav_layout'] : PREF_LAYOUT_DEFAULT,
    ];
}

/**
 * Persist whichever preferences were supplied. Passing null leaves a field alone,
 * so the theme control and the layout control can save independently.
 *
 * @return array{ok: bool, message: string, theme?: string, nav_layout?: string}
 */
function preferences_save(PDO $pdo, int $userId, ?string $theme, ?string $layout): array
{
    $sets = [];
    $params = [];

    if ($theme !== null) {
        if (!pref_valid_theme($theme)) {
            return ['ok' => false, 'message' => 'Invalid theme.'];
        }
        $sets[] = 'theme = ?';
        $params[] = $theme;
    }

    if ($layout !== null) {
        if (!pref_valid_layout($layout)) {
            return ['ok' => false, 'message' => 'Invalid layout.'];
        }
        $sets[] = 'nav_layout = ?';
        $params[] = $layout;
    }

    if (!$sets) {
        return ['ok' => false, 'message' => 'Nothing to update.'];
    }

    $params[] = $userId;
    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE user_id = ?");
    $stmt->execute($params);

    $current = preferences_load($pdo, $userId);

    return [
        'ok' => true,
        'message' => 'Preferences saved.',
        'theme' => $current['theme'],
        'nav_layout' => $current['nav_layout'],
    ];
}
