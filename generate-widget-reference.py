#!/usr/bin/env python3
"""
Generate Elementor widget reference from a live WordPress site.

Usage:
    python generate-widget-reference.py <site-url> <username> <app-password>

Example:
    python generate-widget-reference.py https://example.com admin "xxxx xxxx xxxx xxxx"

Requires the CommunityTech plugin to be active on the target site.
"""
import json, urllib.request, base64, sys, os

if len(sys.argv) == 4:
    site_url, username, password = sys.argv[1], sys.argv[2], sys.argv[3]
elif os.environ.get("WORDPRESS_API_URL"):
    site_url = os.environ["WORDPRESS_API_URL"]
    username = os.environ.get("WORDPRESS_USERNAME", "")
    password = os.environ.get("WORDPRESS_PASSWORD", os.environ.get("WORDPRESS_APP_PASSWORD", ""))
else:
    print("Usage: python generate-widget-reference.py <site-url> <username> <app-password>", file=sys.stderr)
    print("   Or set WORDPRESS_API_URL, WORDPRESS_USERNAME, WORDPRESS_PASSWORD env vars", file=sys.stderr)
    sys.exit(1)

base = f"{site_url.rstrip('/')}/wp-json/communitytech/v1/elementor/widgets"
creds = base64.b64encode(f"{username}:{password}".encode()).decode()

req = urllib.request.Request(base, headers={"Authorization": f"Basic {creds}"})
with urllib.request.urlopen(req) as r:
    data = json.loads(r.read())

widgets = data["widgets"]

skip_names = {"common-base", "common", "common-optimized", "inner-section", "e-component"}
skip_prefixes = ("wp-widget-",)

practical = []
for name, w in widgets.items():
    if name in skip_names:
        continue
    if any(name.startswith(p) for p in skip_prefixes):
        continue
    practical.append(name)

print(f"Fetching {len(practical)} widgets...", file=sys.stderr)

# Aggressively skip sub-property controls — only keep top-level settings
SKIP_CONTAINS = (
    "_font_family", "_font_size", "_font_weight", "_text_transform", "_font_style",
    "_text_decoration", "_line_height", "_letter_spacing", "_word_spacing",
    "_text_shadow",  # keep the _type toggle only
    "_box_shadow",   # keep the _type toggle only
    "_color_stop", "_color_b", "_gradient_type", "_gradient_angle", "_gradient_position",
    "_xpos", "_ypos", "_attachment", "_repeat", "_bg_width",
    "_slideshow_", "_video_link", "_video_start", "_video_end", "_video_fallback",
    "_play_once", "_privacy_mode", "_ken_burns",
    "_blur", "_brightness", "_contrast", "_saturate", "_hue",
    "_stroke_color",
)
SKIP_SUFFIXES = ("_tablet", "_mobile", "_widescreen", "_laptop", "_tablet_extra", "_mobile_extra")
SKIP_TYPES = {"hidden", "section", "tabs", "tab", "alert", "divider", "raw_html", 
              "deprecated_notice", "heading"}
SKIP_EXACT = {
    "background_position", "background_size", "background_image",
    "background_video", "background_color_stop",
}

def should_skip(key, ctrl):
    if key.startswith("_"):
        return True
    if ctrl.get("type") in SKIP_TYPES:
        return True
    if any(key.endswith(s) for s in SKIP_SUFFIXES):
        return True
    if key in SKIP_EXACT:
        return True
    # Skip sub-property patterns
    for pattern in SKIP_CONTAINS:
        if pattern in key:
            # But keep the main toggle (e.g. typography_typography, text_shadow_text_shadow_type)
            if key.endswith("_typography") or key.endswith("_type"):
                return False
            return True
    # Skip hover variants of background sub-controls
    if "hover_" in key and any(p in key for p in ["_position", "_size", "_image", "_video", "_slideshow"]):
        return True
    # Skip css_filters sub-controls (keep only the toggle)
    if "css_filter" in key and not key.endswith("css_filter"):
        return True
    return False

def format_control(key, ctrl):
    ctype = ctrl.get("type", "unknown")
    default = ctrl.get("default", "")
    options = ctrl.get("options", {})
    
    if isinstance(options, list):
        return f"`{key}` ({ctype})"
    
    result = f"`{key}`"
    
    if ctype == "select" and options:
        opt_keys = [k for k in options.keys() if k]
        if opt_keys:
            if len(opt_keys) <= 6:
                result += f" ({', '.join(opt_keys)})"
            else:
                result += f" ({', '.join(opt_keys[:5])}, +{len(opt_keys)-5})"
    elif ctype == "choose" and isinstance(options, dict):
        opt_keys = list(options.keys())
        result += f" ({', '.join(opt_keys)})"
    elif ctype == "repeater":
        result += " (repeater)"
    elif ctype == "switcher":
        result += " (on/off)"
    elif ctype in ("color",):
        result += " (color)"
    elif ctype == "slider":
        if default and isinstance(default, dict) and default.get("size"):
            result += f" (slider, default: {default['size']}{default.get('unit','')})"
        else:
            result += " (slider)"
    elif ctype == "dimensions":
        result += " (dimensions)"
    elif ctype in ("media",):
        result += " (media)"
    elif ctype in ("gallery",):
        result += " (gallery)"
    elif ctype in ("icons",):
        result += " (icons)"
    elif ctype in ("url",):
        result += " (url)"
    elif ctype in ("wysiwyg",):
        result += " (wysiwyg)"
    elif ctype in ("text", "textarea", "number"):
        if default and isinstance(default, str) and len(default) < 30:
            result += f' ({ctype}, default: "{default}")'
        else:
            result += f" ({ctype})"
    else:
        result += f" ({ctype})"
    
    return result

def format_widget(name, detail):
    lines = []
    title = detail.get("title", name)
    keywords = detail.get("keywords", [])
    controls = detail.get("controls", {})
    
    lines.append(f"### `{name}` — {title}")
    if keywords:
        lines.append(f"*{', '.join(keywords)}*")
    
    # Collect section metadata
    section_labels = {}
    section_tabs = {}
    for key, ctrl in controls.items():
        if ctrl.get("type") == "section":
            section_labels[key] = ctrl.get("label", key)
            section_tabs[key] = ctrl.get("tab", "content")
    
    # Group
    grouped = {}
    for key, ctrl in controls.items():
        if should_skip(key, ctrl):
            continue
        section = ctrl.get("section", "other")
        tab = ctrl.get("tab", section_tabs.get(section, "content"))
        if tab == "advanced":
            continue
        group_key = f"{tab}|{section}"
        if group_key not in grouped:
            label = section_labels.get(section, section)
            grouped[group_key] = {"label": label, "tab": tab, "controls": []}
        grouped[group_key]["controls"].append((key, ctrl))
    
    for tab_name in ["content", "style"]:
        tab_groups = [(k, v) for k, v in grouped.items() if v["tab"] == tab_name]
        if not tab_groups:
            continue
        
        for group_key, group in tab_groups:
            ctrl_strs = [format_control(k, c) for k, c in group["controls"]]
            if ctrl_strs:
                label = group["label"]
                lines.append(f"- **{label}** ({tab_name}): {' · '.join(ctrl_strs)}")
    
    return "\n".join(lines)


output_by_cat = {}
category_order = ["basic", "general", "pro-elements", "theme-elements", 
                   "theme-elements-single", "theme-elements-archive", "link-in-bio"]

for i, name in enumerate(sorted(practical)):
    try:
        req = urllib.request.Request(f"{base}/{name}", headers={"Authorization": f"Basic {creds}"})
        with urllib.request.urlopen(req) as r:
            detail = json.loads(r.read())
        cats = detail.get("categories", ["uncategorized"])
        primary_cat = cats[0] if cats else "uncategorized"
        if primary_cat not in output_by_cat:
            output_by_cat[primary_cat] = []
        output_by_cat[primary_cat].append(format_widget(name, detail))
        if (i+1) % 20 == 0:
            print(f"  {i+1}/{len(practical)}...", file=sys.stderr)
    except Exception as e:
        print(f"  SKIP {name}: {e}", file=sys.stderr)

md = []
md.append("# Elementor Widget Reference")
md.append("")
md.append("Auto-generated from the CommunityTech Widget Registry API on thailand.thesilkworthproject.com.")
md.append("To regenerate: query `GET /wp-json/communitytech/v1/elementor/widgets/<name>` for each widget.")
md.append("")
md.append("## Usage Notes")
md.append("")
md.append("- **Colors**: always use `__globals__` references — `\"__globals__\": {\"title_color\": \"globals/colors?id=primary\"}`")
md.append("- **Typography**: use `__globals__` — `\"__globals__\": {\"typography_typography\": \"globals/typography?id=primary\"}`")
md.append("- **Advanced tab** (margins, padding, motion effects, custom CSS): identical for all widgets, not shown here.")
md.append("- **Responsive variants** (`_tablet`, `_mobile`): not shown — append suffix to any control key.")
md.append("- **Full schema**: `GET /wp-json/communitytech/v1/elementor/widgets/<name>` returns every control.")
md.append("")
md.append("---")

for cat in category_order:
    if cat not in output_by_cat:
        continue
    md.append("")
    md.append(f"## {cat}")
    md.append("")
    for widget_md in output_by_cat[cat]:
        md.append(widget_md)
        md.append("")

for cat in sorted(output_by_cat.keys()):
    if cat in category_order:
        continue
    md.append("")
    md.append(f"## {cat}")
    md.append("")
    for widget_md in output_by_cat[cat]:
        md.append(widget_md)
        md.append("")

print("\n".join(md))
