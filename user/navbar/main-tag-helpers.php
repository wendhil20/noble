<?php
// tag_helpers.php
// Include this in: shop nav, main-category-product-page.php, manage-tags.php

function get_tag_config(mysqli $conn): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    $result = $conn->query("SELECT slug, label, bg, color, dot, border FROM tag_config ORDER BY slug");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cache[$row['slug']] = $row;
        }
        $result->free();
    }
    return $cache;
}

// Used in shop nav dropdown
function nav_tag_badge(string $tag, mysqli $conn): string {
    if ($tag === 'normal' || $tag === '') return '';
    $cfg = get_tag_config($conn);
    if (!isset($cfg[$tag])) return '';
    $b      = $cfg[$tag];
    $label  = htmlspecialchars($b['label'], ENT_QUOTES);
    $bg     = preg_replace('/[^#a-zA-Z0-9(),. %]/', '', $b['bg']);
    $color  = preg_replace('/[^#a-zA-Z0-9(),. %]/', '', $b['color']);
    $dot    = preg_replace('/[^#a-zA-Z0-9(),. %]/', '', $b['dot']);
    $border = preg_replace('/[^#a-zA-Z0-9(),. %]/', '', $b['border']);
    return sprintf(
        '<span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;'
        . 'padding:1px 6px;border-radius:5px;background:%s;color:%s;border:1px solid %s;line-height:1.6;white-space:nowrap;">'
        . '<span style="width:5px;height:5px;border-radius:50%%;background:%s;display:inline-block;"></span>'
        . '%s</span>',
        $bg, $color, $border, $dot, $label
    );
}

// Used in admin category page
function tag_badge(string $tag, mysqli $conn): string {
    if ($tag === 'normal' || $tag === '') return '';
    $cfg = get_tag_config($conn);
    if (!isset($cfg[$tag])) return '';
    $b      = $cfg[$tag];
    $label  = htmlspecialchars($b['label'], ENT_QUOTES);
    $bg     = preg_replace('/[^#a-zA-Z0-9(),. %]/', '', $b['bg']);
    $color  = preg_replace('/[^#a-zA-Z0-9(),. %]/', '', $b['color']);
    $border = preg_replace('/[^#a-zA-Z0-9(),. %]/', '', $b['border']);
    return sprintf(
        '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;'
        . 'background:%s;color:%s;border:1px solid %s;">%s</span>',
        $bg, $color, $border, $label
    );
}

// Used in admin dropdowns — ['normal'=>['label'=>'Normal'], 'best_offer'=>['label'=>'Best Offer'], ...]
function get_tag_options(mysqli $conn): array {
    $options = ['normal' => ['label' => 'Normal']];
    foreach (get_tag_config($conn) as $slug => $data) {
        $options[$slug] = ['label' => $data['label']];
    }
    return $options;
}