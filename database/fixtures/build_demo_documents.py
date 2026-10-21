"""Regenerate the clearly labelled PDFs used by draft document seed records."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DESTINATION = ROOT / 'public/assets/documents/demo'
assert DESTINATION.resolve().is_relative_to(ROOT.resolve())
DESTINATION.mkdir(parents=True, exist_ok=True)


def make_pdf(title, lines):
    def escape(text):
        return text.replace('\\', '\\\\').replace('(', '\\(').replace(')', '\\)')
    commands = ['0.12 0.12 0.12 rg 0 690 612 102 re f',
                '1 0.82 0.19 rg BT /F1 22 Tf 40 748 Td (SUPERKING - DEMO ATTACHMENT) Tj ET',
                '0.76 0.07 0.12 rg BT /F1 18 Tf 40 650 Td (NOT APPROVED FOR PUBLICATION) Tj ET',
                '0.12 0.12 0.12 rg BT /F1 17 Tf 40 612 Td (' + escape(title) + ') Tj ET']
    for index, text in enumerate(lines):
        commands.append('BT /F1 11 Tf 40 ' + str(570-index*23) + ' Td (' + escape(text) + ') Tj ET')
    stream = '\n'.join(commands).encode('ascii')
    objects = [b'<< /Type /Catalog /Pages 2 0 R >>',
               b'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
               b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
               b'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
               b'<< /Length ' + str(len(stream)).encode() + b' >>\nstream\n' + stream + b'\nendstream']
    output = bytearray(b'%PDF-1.4\n')
    offsets = [0]
    for index, data in enumerate(objects, 1):
        offsets.append(len(output))
        output.extend(str(index).encode() + b' 0 obj\n' + data + b'\nendobj\n')
    start = len(output)
    output.extend(b'xref\n0 6\n0000000000 65535 f \n')
    for offset in offsets[1:]:
        output.extend(f'{offset:010d} 00000 n \n'.encode())
    output.extend(b'trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n' + str(start).encode() + b'\n%%EOF\n')
    return bytes(output)


documents = {
    'demo-company-profile.pdf': ('Company profile training attachment', ['This is a demonstration file for the staff document workflow.', 'It is not an approved company profile or commercial offer.', 'Replace it with the company-approved profile before publication.']),
    'demo-product-catalogue.pdf': ('Product catalogue training attachment', ['This file demonstrates PDF uploads and download links.', 'It contains no approved product range, prices or specifications.', 'Replace it with a genuine, approved SUPERKING product catalogue.']),
    'demo-technical-document.pdf': ('Technical-document training attachment', ['This is not a Technical Data Sheet or Safety Data Sheet.', 'No product values, performance claims or approvals are stated.', 'Do not use this file for product selection, safety or handling.']),
    'demo-certificate-placeholder.pdf': ('Certificate placeholder - NOT A CERTIFICATE', ['This is a training placeholder only. It is not a certificate.', 'No ISO, API, JASO, OEM or other certification is claimed.', 'Upload valid, company-approved documentation instead.']),
}
for filename, (title, lines) in documents.items():
    (DESTINATION / filename).write_bytes(make_pdf(title, lines + ['', 'DEMO ONLY. Not for commercial distribution.']))
print('Created four labelled demo PDF fixtures.')
