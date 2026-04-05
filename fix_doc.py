import zipfile, shutil, copy, os, sys
sys.stdout.reconfigure(encoding='utf-8')
from lxml import etree

W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
W = '{' + W_NS + '}'
DML_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main'
A = '{' + DML_NS + '}'

PATH = 'ITE-206L-Final-Project-Documentation-Format (1).docx'
TMP  = PATH + '.bak'

shutil.copy2(PATH, TMP)
print('Backup created:', TMP)

def get_text(para):
    return ''.join((t.text or '') for t in para.iter(W + 't'))

def fix_document(data):
    tree = etree.fromstring(data)
    body = tree.find(W + 'body')
    paras = list(body)
    changed = []

    for idx, elem in enumerate(paras):
        if elem.tag != W + 'p':
            changed.append(elem)
            continue

        text = get_text(elem)

        # Fix 1: Fonts — replace Times New Roman and Aptos with Book Antiqua
        for rPr in elem.iter(W + 'rPr'):
            rFonts = rPr.find(W + 'rFonts')
            if rFonts is not None:
                for attr in list(rFonts.attrib):
                    val = rFonts.get(attr, '')
                    if val in ('Times New Roman', 'Aptos'):
                        rFonts.set(attr, 'Book Antiqua')
                # Remove theme-font attributes that resolve to Aptos
                theme_attrs = [a for a in rFonts.attrib if 'Theme' in a.split('}')[-1]]
                for a in theme_attrs:
                    del rFonts.attrib[a]

        # Fix 2: Line spacing — single (240) to 1.5 (360)
        pPr = elem.find(W + 'pPr')
        if pPr is not None:
            spacing = pPr.find(W + 'spacing')
            if spacing is not None:
                line_val = spacing.get(W + 'line', '')
                if line_val == '240':
                    spacing.set(W + 'line', '360')
                    spacing.set(W + 'lineRule', 'auto')
            # Add explicit 1.5 spacing to paragraphs that inherit (and have text)
            if pPr.find(W + 'spacing') is None and text.strip():
                spacing = etree.SubElement(pPr, W + 'spacing')
                spacing.set(W + 'line', '360')
                spacing.set(W + 'lineRule', 'auto')

        # Fix 3a: Split "Scope" heading from content
        if text.startswith('Scope') and 'The system includes' in text:
            runs = list(elem.findall(W + 'r'))
            # First bold run = heading; remaining = content
            heading_run = None
            content_runs = []
            for r in runs:
                rp = r.find(W + 'rPr')
                is_bold = rp is not None and rp.find(W + 'b') is not None
                run_text = get_text(r).strip()
                if heading_run is None and is_bold and run_text == 'Scope':
                    heading_run = r
                else:
                    content_runs.append(r)

            if heading_run is None:
                heading_run = runs[0]
                content_runs = runs[1:]

            # Build heading paragraph
            head_p = etree.Element(W + 'p')
            if pPr is not None:
                head_pPr = copy.deepcopy(pPr)
                # Set left alignment
                jc = head_pPr.find(W + 'jc')
                if jc is None:
                    jc = etree.SubElement(head_pPr, W + 'jc')
                jc.set(W + 'val', 'left')
                head_p.append(head_pPr)
            head_p.append(copy.deepcopy(heading_run))
            changed.append(head_p)

            # Build content paragraph (keep original pPr)
            content_p = etree.Element(W + 'p')
            if pPr is not None:
                content_p.append(copy.deepcopy(pPr))
            for r in content_runs:
                content_p.append(copy.deepcopy(r))
            changed.append(content_p)
            print(f'  Fixed: Scope heading split (body index {idx})')
            continue

        # Fix 3b: Split "Limitation" heading from content
        if text.startswith('Limitation') and 'current version' in text:
            runs = list(elem.findall(W + 'r'))
            heading_run = None
            content_runs = []
            for r in runs:
                rp = r.find(W + 'rPr')
                is_bold = rp is not None and rp.find(W + 'b') is not None
                run_text = get_text(r).strip()
                if heading_run is None and is_bold and run_text == 'Limitation':
                    heading_run = r
                else:
                    content_runs.append(r)

            if heading_run is None:
                heading_run = runs[0]
                content_runs = runs[1:]

            head_p = etree.Element(W + 'p')
            if pPr is not None:
                head_pPr = copy.deepcopy(pPr)
                jc = head_pPr.find(W + 'jc')
                if jc is None:
                    jc = etree.SubElement(head_pPr, W + 'jc')
                jc.set(W + 'val', 'left')
                head_p.append(head_pPr)
            head_p.append(copy.deepcopy(heading_run))
            changed.append(head_p)

            content_p = etree.Element(W + 'p')
            if pPr is not None:
                content_p.append(copy.deepcopy(pPr))
            for r in content_runs:
                content_p.append(copy.deepcopy(r))
            changed.append(content_p)
            print(f'  Fixed: Limitation heading split (body index {idx})')
            continue

        # Fix 4: Remove standalone "Error handling" TOC entry
        # Identified as: contains "Error handling" + dot leaders, no chapter reference
        if ('Error handling' in text and '.' * 5 in text
                and 'Chapter' not in text and len(text.strip()) < 120):
            print(f'  Fixed: Removed Error handling TOC entry (body index {idx})')
            continue  # drop this paragraph

        changed.append(elem)

    # Rebuild body
    for child in list(body):
        body.remove(child)
    for child in changed:
        body.append(child)

    return etree.tostring(tree, xml_declaration=True, encoding='UTF-8', standalone=True)


def fix_theme(data):
    tree = etree.fromstring(data)
    for elem in tree.iter(A + 'minorFont'):
        latin = elem.find(A + 'latin')
        if latin is not None:
            old = latin.get('typeface', '')
            latin.set('typeface', 'Book Antiqua')
            print(f'  Fixed: Theme minor font {old!r} -> Book Antiqua')
    return etree.tostring(tree, xml_declaration=True, encoding='UTF-8', standalone=True)


# Process the file
with zipfile.ZipFile(PATH, 'r') as zin:
    with zipfile.ZipFile(PATH + '.new', 'w', zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename == 'word/document.xml':
                print('Processing document.xml ...')
                data = fix_document(data)
            elif item.filename == 'word/theme/theme1.xml':
                print('Processing theme1.xml ...')
                data = fix_theme(data)
            zout.writestr(item, data)

os.replace(PATH + '.new', PATH)
print()
print('Done. All fixes applied.')
