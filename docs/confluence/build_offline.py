#!/usr/bin/env python3
"""Builds ClockInOut-Docs-Offline.zip: the Confluence pages as plain HTML with local screenshots,
plus the master guide and blueprint, readable without internet.

Usage: python3 docs/confluence/build_offline.py OUTPUT.zip [extra-private-page.html ...]
Private pages (e.g. the credentials register exported from Confluence) are added only when passed
as arguments; they are never stored in this public repository."""
import html, json, os, re, sys, zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.dirname(HERE)
CSS = """body{font-family:Arial,Helvetica,sans-serif;max-width:1100px;margin:24px auto;padding:0 16px;color:#172b4d;line-height:1.5}
table{border-collapse:collapse;width:100%;margin:12px 0}th,td{border:1px solid #dfe1e6;padding:6px 8px;text-align:left;vertical-align:top}
th{background:#f4f5f7}img{max-width:100%;border:1px solid #dfe1e6;border-radius:6px;margin:8px 0}code{background:#f4f5f7;padding:1px 4px;border-radius:3px}
pre{background:#f4f5f7;padding:10px;overflow:auto}[data-type^=panel]{border-left:4px solid #4c9aff;background:#deebff;padding:6px 12px;margin:12px 0}
[data-type=panel-warning]{border-color:#ffab00;background:#fffae6}[data-type=panel-note]{border-color:#6554c0;background:#eae6ff}
details{border:1px solid #dfe1e6;border-radius:4px;padding:6px 10px;margin:6px 0}summary{font-weight:bold;cursor:pointer}nav a{margin-right:12px}"""

def page(title, body):
    return (f'<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            f'<title>{html.escape(title)}</title><style>{CSS}</style></head><body><nav><a href="index.html">← All pages</a></nav>'
            f'<h1>{html.escape(title)}</h1>{body}</body></html>')

def main():
    out = sys.argv[1]
    cfg = json.load(open(os.path.join(HERE, 'pages.json')))
    links = []
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
        for p in cfg['pages']:
            src = os.path.normpath(os.path.join(HERE, p['file']))
            name = re.sub(r'[^a-z0-9]+', '-', p['title'].lower()).strip('-') + '.html'
            if p.get('format') == 'markdown':
                body = '<pre style="white-space:pre-wrap">' + html.escape(open(src).read()) + '</pre>'
            else:
                body = open(src).read().replace('{{IMGROOT}}', 'img').replace(cfg['image_base'], 'img')
            z.writestr(name, page(p['title'], body))
            links.append((p['title'], name))
        for extra in sys.argv[2:]:
            title = os.path.splitext(os.path.basename(extra))[0].replace('-', ' ').capitalize()
            name = os.path.basename(extra)
            z.writestr(name, page(title, open(extra).read().replace('{{IMGROOT}}', 'img')))
            links.append((title + ' (private)', name))
        img = os.path.join(DOCS, 'img')
        for root, _, files in os.walk(img):
            for f in files:
                full = os.path.join(root, f)
                z.write(full, os.path.join('img', os.path.relpath(full, img)))
        for md in ('MASTER_GUIDE.md', 'SAAS_BLUEPRINT.md', 'GO_LIVE.md'):
            z.write(os.path.join(DOCS, md), md)
        idx = ''.join(f'<li><a href="{n}">{html.escape(t)}</a></li>' for t, n in links)
        z.writestr('index.html', page(cfg['home_title'] + ' (offline copy)',
            '<p>Offline copy of the ClockInOut documentation. Open any page below; screenshots are in <code>img/</code>. '
            'The full technical reference is <code>MASTER_GUIDE.md</code> (open with any text editor).</p><ol>' + idx + '</ol>'))

if __name__ == '__main__':
    main()
