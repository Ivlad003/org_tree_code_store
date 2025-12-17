# Data Mismatch Report: staff.json vs files.json

**Generated:** 2025-12-17
**Total Staff Members:** 58 (excluding category headers)
**Total Image Files:** 54 (including logo)

---

## Executive Summary

| Category | Count | Priority |
|----------|-------|----------|
| Staff missing images | 7 | HIGH |
| Orphan images (no matching staff) | 1 | MEDIUM |
| Name spelling mismatches | 3 | MEDIUM |
| Filename format issues | 5 | MEDIUM |
| Trailing spaces in staff names | 4 | LOW |
| Duplicate images | 1 | LOW |
| Invalid file formats | 2 | HIGH |

---

## 1. Staff WITHOUT Matching Images (7 people) - HIGH PRIORITY

These staff members have no photo in Google Drive:

| # | Staff Name | Action Required |
|---|------------|-----------------|
| 1 | **Andrii Slynchuk** | Upload photo as `Andrii Slynchuk.jpg` |
| 2 | **Denys Davydov** | Upload photo as `Denys Davydov.jpg` |
| 3 | **Denys Ukhach** | Upload photo as `Denys Ukhach.jpg` |
| 4 | **Diana Murdasova** | Upload photo as `Diana Murdasova.jpg` |
| 5 | **Mykhailo Marchuk** | Upload photo as `Mykhailo Marchuk.jpg` |
| 6 | **Paul Smith** | Upload photo as `Paul Smith.jpg` |
| 7 | **Vitalii Kenyiz** | Upload photo as `Vitalii Kenyiz.jpg` |

### Recommendation
Request photos from these 7 staff members and upload to the "For Corporate Tree" Google Drive folder.

---

## 2. Orphan Images (No Matching Staff) - MEDIUM PRIORITY

These images exist but have no corresponding staff member:

| # | Filename | Google Drive ID | Action Required |
|---|----------|-----------------|-----------------|
| 1 | `Alla Krukovska.jpg` | `1AYF5bIjE6WJSg_-txkEAU4-OVwmc6Jhx` | Add to staff.json OR delete from Drive |
| 2 | `logo.png` | `18DpEoimVPqQHZ1zxtONtgAakf6wUHjUZ` | Keep (company logo, not a person) |

### Recommendation
- Verify if Alla Krukovska is a current employee. If yes, add to staff list. If no, archive the image.

---

## 3. Name Spelling Mismatches - MEDIUM PRIORITY

Names that don't match exactly between staff list and image files:

| # | Staff Name | Image Filename | Issue | Recommendation |
|---|------------|----------------|-------|----------------|
| 1 | `Valeriia Smoliak` | `Valeria Smoliak.jpg` | Missing 'i' in image | Rename file to `Valeriia Smoliak.jpg` |
| 2 | `Zenyk (Zinovii) Haiduk` | `Zinovii Haiduk.jpg` | Staff has nickname | Rename file to `Zenyk Haiduk.jpg` OR update staff name to `Zinovii Haiduk` |
| 3 | `Yelyzaveta Sidorenko` | `Yelyzaveta Sidorenko 2.jpg` | Extra " 2" suffix | Rename file to `Yelyzaveta Sidorenko.jpg` |

### Recommendation
Standardize naming convention. Either:
- **Option A:** Rename image files to match staff names exactly
- **Option B:** Update n8n workflow to handle these variations (already implemented)

---

## 4. Filename Format Issues - MEDIUM PRIORITY

Files with formatting problems that may cause matching failures:

| # | Current Filename | Issue | Corrected Filename |
|---|------------------|-------|-------------------|
| 1 | `Baptiste Rucin].jpg` | Extra `]` bracket | `Baptiste Rucin.jpg` |
| 2 | `Ruslana Chmut .jpeg` | Space before extension | `Ruslana Chmut.jpeg` |
| 3 | `Oksana Shmiliak .png` | Space before extension | `Oksana Shmiliak.png` |
| 4 | `Artem Tkachov.pdf` | PDF, not an image | Convert to `Artem Tkachov.jpg` |
| 5 | `Daniil Rosomakha.HEIC` | HEIC not web-compatible | Convert to `Daniil Rosomakha.jpg` |

### Recommendation
1. Rename files to remove typos and extra spaces
2. Convert non-web formats (PDF, HEIC) to JPG or PNG

---

## 5. Trailing Spaces in Staff Names - LOW PRIORITY

Staff names with trailing/extra spaces in staff.json:

| # | Current Name | Spaces | Corrected Name |
|---|--------------|--------|----------------|
| 1 | `"Dimitri Damotte "` | 1 trailing | `"Dimitri Damotte"` |
| 2 | `"Oleksandr Anpilov  "` | 2 trailing | `"Oleksandr Anpilov"` |
| 3 | `"Ruslana Chmut "` | 1 trailing | `"Ruslana Chmut"` |
| 4 | `"Valeriia Smoliak "` | 1 trailing | `"Valeriia Smoliak"` |

### Recommendation
Clean up the source Google Sheet to remove trailing spaces from names.

---

## 6. Duplicate Images - LOW PRIORITY

Files that appear multiple times:

| Filename | Occurrences | Google Drive IDs |
|----------|-------------|------------------|
| `Victoria Buhai.jpg` | 2 | `10wTrfWWu-vNo27Wg6lG5bSNGg-eCfjZe`, `1nrMZ-A8ARVQHhzNym7tj45SEXgvo0wQ1` |

### Recommendation
Keep the better quality image and delete the duplicate to avoid confusion.

---

## 7. Invalid File Formats - HIGH PRIORITY

Files that cannot be displayed on web:

| # | Filename | Format | Issue | Solution |
|---|----------|--------|-------|----------|
| 1 | `Artem Tkachov.pdf` | PDF | Not an image format | Request actual photo or extract image from PDF |
| 2 | `Daniil Rosomakha.HEIC` | HEIC | Apple format, not web-compatible | Convert to JPG using online converter or macOS Preview |

### Recommendation
Convert these files to standard web formats (JPG, PNG, or WebP).

---

## Complete Matching Status

### Successfully Matched (47 staff with images)

| Staff Name | Image File | Status |
|------------|------------|--------|
| Abilkaiyr Tursunov | Abilkaiyr Tursunov.jpg | OK |
| Aleksandr Shumenko | Aleksandr Shumenko.jpg | OK |
| Alyona Rapova | Alyona Rapova.jpg | OK |
| Andrii Polishchuk | Andrii Polishchuk.jpg | OK |
| Andrii Rachynskyi | Andrii Rachynskyi.jpg | OK |
| Anna-Maria Polovykh | Anna-Maria Polovykh.jpg | OK |
| Artem Tkachov | Artem Tkachov.pdf | FORMAT ISSUE |
| Arthur Murauskas | Arthur Murauskas.png | OK |
| Baptiste Rucin | Baptiste Rucin].jpg | TYPO IN FILENAME |
| Bertrand Merle | Bertrand Merle.png | OK |
| Bogdan Fedorko | Bogdan Fedorko.jpg | OK |
| Daniil Rosomakha | Daniil Rosomakha.HEIC | FORMAT ISSUE |
| Daphné Vogelgesang | Daphné Vogelgesang.jpg | OK |
| David Maechler | David Maechler.jpg | OK |
| Dimitri Damotte | Dimitri Damotte.jpg | OK |
| Dmytro Rybalchenko | Dmytro Rybalchenko.jpg | OK |
| Dmytro Velychko | Dmytro Velychko.jpg | OK |
| Edem Lawson-Body | Edem Lawson-Body.jpg | OK |
| Eugene Kulishov | Eugene Kulishov.jpg | OK |
| Iakov Kemer | Iakov Kemer.jpg | OK |
| Kyrylo Kliushnyk | Kyrylo Kliushnyk.jpg | OK |
| Lucas Baron | Lucas Baron.jpeg | OK |
| Maksym Krestnykov | Maksym Krestnykov.jpg | OK |
| Maksym Sadovyi | Maksym Sadovyi.jpg | OK |
| Maksym Sydorov | Maksym Sydorov.jpg | OK |
| Marian Marychak | Marian Marychak.jpg | OK |
| Martin Chiquet | Martin Chiquet.jpg | OK |
| Maxime Topolov | Maxime Topolov.jpeg | OK |
| Nikita Panasenko | Nikita Panasenko.jpg | OK |
| Oksana Shmiliak | Oksana Shmiliak .png | SPACE IN FILENAME |
| Oleksandr Anpilov | Oleksandr Anpilov.jpg | OK |
| Oleksandr Borodavchenko | Oleksandr Borodavchenko.jpg | OK |
| Oleksandr Timoshchenko | Oleksandr Timoshchenko.jpg | OK |
| Olena Ilina | Olena Ilina.jpg | OK |
| Olena Naidenko | Olena Naidenko.jpg | OK |
| Olena Sevastian | Olena Sevastian.jpg | OK |
| Olga Zviezdicheva | Olga Zviezdicheva.jpg | OK |
| Ruslan Mamedov | Ruslan Mamedov.jpg | OK |
| Ruslana Chmut | Ruslana Chmut .jpeg | SPACE IN FILENAME |
| Ryan Chiesi | Ryan Chiesi.jpg | OK |
| Serhii Soloviov | Serhii Soloviov.jpg | OK |
| Stepan Tabaka | Stepan Tabaka.png | OK |
| Tymur Kabulov | Tymur Kabulov.png | OK |
| Valeriia Smoliak | Valeria Smoliak.jpg | SPELLING MISMATCH |
| Victoria Buhai | Victoria Buhai.jpg | OK (has duplicate) |
| Vladyslav Kosmach | Vladyslav Kosmach.png | OK |
| Volodymyr Lysenko | Volodymyr Lysenko.jpg | OK |
| Yelyzaveta Sidorenko | Yelyzaveta Sidorenko 2.jpg | SUFFIX IN FILENAME |
| Zenyk (Zinovii) Haiduk | Zinovii Haiduk.jpg | NICKNAME MISMATCH |
| Zhanna Chaikovska | Zhanna Chaikovska.JPEG | OK |
| Zhanna Pichkurova | Zhanna Pichkurova.png | OK |

---

## Action Items Checklist

### Immediate (HIGH Priority)
- [ ] Upload photos for 7 missing staff members
- [ ] Convert `Artem Tkachov.pdf` to JPG
- [ ] Convert `Daniil Rosomakha.HEIC` to JPG

### Short-term (MEDIUM Priority)
- [ ] Rename `Baptiste Rucin].jpg` to `Baptiste Rucin.jpg`
- [ ] Rename `Ruslana Chmut .jpeg` to `Ruslana Chmut.jpeg`
- [ ] Rename `Oksana Shmiliak .png` to `Oksana Shmiliak.png`
- [ ] Rename `Valeria Smoliak.jpg` to `Valeriia Smoliak.jpg`
- [ ] Rename `Yelyzaveta Sidorenko 2.jpg` to `Yelyzaveta Sidorenko.jpg`
- [ ] Decide on Zenyk/Zinovii naming convention
- [ ] Verify if Alla Krukovska is current staff

### Maintenance (LOW Priority)
- [ ] Remove trailing spaces from staff names in source sheet
- [ ] Delete duplicate Victoria Buhai image
- [ ] Clean up staff.json (remove ~50x duplicate data)

---

## Technical Notes

### n8n Workflow Updates Applied
The n8n workflow has been updated to handle:
- Spelling variations (Valeriia/Valeria, etc.)
- Names with parentheses like "Zenyk (Zinovii) Haiduk"
- Numbered filenames like "Name 2.jpg"
- Trailing spaces in names
- Invalid file formats (PDF excluded)

### app.js Fixes Applied
The website JavaScript has been updated to:
- Filter out empty rows
- Fix comma-separated names
- Correct QA/BDR category mismatch

---

## Files Reference

| File | Google Drive ID |
|------|-----------------|
| David Maechler.jpg | 1WVmqMgVj52Xqht01NnoxBT5UqdJzC4jM |
| Eugene Kulishov.jpg | 1SDN_uKuIfXLf3u1WQ063lhyF0fcO_M1Q |
| Maksym Sydorov.jpg | 1y9bIWVv4AUiBMJifreLsokYjp00p1k3y |
| Oleksandr Borodavchenko.jpg | 1YmcUV6F9Q2Umkllji0J25Z_Fvn5BHzEL |
| Daphné Vogelgesang.jpg | 10decAjTU741lW-_a-8fmiV-9sweGrZgy |
| Kyrylo Kliushnyk.jpg | 1jA9OOr1AFbQTuxw2jis2Jmr7zczFOvE- |
| Serhii Soloviov.jpg | 1-wMFVpXpVH5zG_002u4h4z87c_iZrJT_ |
| Maksym Krestnykov.jpg | 17V4BJv8-RuD89V66H83EA4L0Mj7OQDrG |
| Andrii Rachynskyi.jpg | 1op7ysdIeQ89la11UkCyeiWYbosukWAiK |
| Valeria Smoliak.jpg | 1_-DJHeZnm10IclNY0gDdTZbHJIXGQFdc |
| Zhanna Pichkurova.png | 1yYSOmu82zVH8_n9mgBtF6Gt0flm9kStf |
| Ryan Chiesi.jpg | 1qggwaawCxduASkWhvF43toMYDAzl6TPm |
| Victoria Buhai.jpg | 10wTrfWWu-vNo27Wg6lG5bSNGg-eCfjZe |
| Dimitri Damotte.jpg | 1dmFc_0BbzgkdR89YjXk8gKVZfk99mIIJ |
| Ruslana Chmut .jpeg | 1EDOckv0bxOys_zHuyQk3B_9OZwQVhxXO |
| Edem Lawson-Body.jpg | 1aLrxGz3Fn617Fz4vvhyE5n5p7PUI8Qs3 |
| Oksana Shmiliak .png | 1yMWBZDhsVuzZqoz6cypmXpGjzNhIlIcK |
| Martin Chiquet.jpg | 17mxU_41BLNyDt4H0ugjLSNVskZEqqkTI |
| Lucas Baron.jpeg | 1dTW6me5G9LwnMBL0nL2y6R8QuylS_9qI |
| Arthur Murauskas.png | 1dHBAzOf8UBl8H417tFhenfTCPL01cpXe |
| Bertrand Merle.png | 1Duqr5dj3oza7NETtlkfHLP2h3BWx4uw1 |
| Maxime Topolov.jpeg | 1DG_k8C-4sQ_sqoFUZKCXhMWxBenPHIiq |
| logo.png | 18DpEoimVPqQHZ1zxtONtgAakf6wUHjUZ |
| Baptiste Rucin].jpg | 1d-A_NM2VsbA6vBpkg1Lh2bfDj207fcpJ |
| Anna-Maria Polovykh.jpg | 1nos2mniP6WSo4lNstw5uBgvAq4ML7bz4 |
| Olena Sevastian.jpg | 1r-e3Q7naN_AaK0vo35rCEfdtr3ZkHOPn |
| Abilkaiyr Tursunov.jpg | 1JGSgN9lyDZXQ9oWv4CADJZPPa22kCjnO |
| Bogdan Fedorko.jpg | 1Gh077ODUuXDOpUS8KvX03cO13NQ-GjT1 |
| Yelyzaveta Sidorenko 2.jpg | 1oRWS7VW5EZu6L9ul2D2-I5EYEQFmB7oo |
| Oleksandr Anpilov.jpg | 1VJUACBnqGmwP8f3q3vYH6eXQA-AmayVP |
| Daniil Rosomakha.HEIC | 1_JxGT8LXULbTUe754DQfuttpB7Vg71hO |
| Dmytro Rybalchenko.jpg | 1rgmAr1kHRUflSuz_dbslfq08Ss49K-7a |
| Olena Naidenko.jpg | 1AtisCMSxcJBgyHUF8XwXuODVChNwXFuX |
| Nikita Panasenko.jpg | 1ANPTfhfLsvGc6MgbUZJBaoXhsCsjhkHN |
| Andrii Polishchuk.jpg | 1Kvmfb_F3nwm2_hrRMLa9NSBNnVtPuR3T |
| Dmytro Velychko.jpg | 1OkLxjpaObuUXuM2-W_HeA-nedcqIguZt |
| Volodymyr Lysenko.jpg | 15IpZBFtZRs5ztISK7oz5TbWO_c-eePWT |
| Maksym Sadovyi.jpg | 1GGY2UqALYsWopDZml6mH9oJbFqMKCGFB |
| Alla Krukovska.jpg | 1AYF5bIjE6WJSg_-txkEAU4-OVwmc6Jhx |
| Marian Marychak.jpg | 1GdWyh2vu13EATko52rxDId9cPMmed6qi |
| Olena Ilina.jpg | 1IkgqbNdYoVY709IfgxWuNL3u4YKuhth_ |
| Zinovii Haiduk.jpg | 1RbEmDbKzEanyYS4fKbjpeTAwcWK5fdLz |
| Zhanna Chaikovska.JPEG | 1-HejmfXbzydvSVT5fcRd43yU5o3-ICMF |
| Victoria Buhai.jpg (dup) | 1nrMZ-A8ARVQHhzNym7tj45SEXgvo0wQ1 |
| Tymur Kabulov.png | 1-3UIfwnyvd2x11oJLfcpmOef3L5-JNE4 |
| Iakov Kemer.jpg | 1S_dm1XFrgACNmaY5uHAh5mFzhXC1A5oo |
| Olga Zviezdicheva.jpg | 1Mp1v6PCBPYcNakS2ToztIN_-1VL6ktOM |
| Oleksandr Timoshchenko.jpg | 1Ix6SeTA2U87bIIZRygKiakdoy_TwSbRX |
| Alyona Rapova.jpg | 1DFZ9-hA07_09ZaQH9oC1qliSVFKJ3HKh |
| Ruslan Mamedov.jpg | 1_Cwg5Rp6jPZ1Bm2xmEizj4kihQl_2UFB |
| Artem Tkachov.pdf | 1wFsg6uVD3bY8OhrvFqX5Ngbz6HYT3kHi |
| Aleksandr Shumenko.jpg | 1lwMwk3Ie2dEQrY8EUoRMevD78aldsHP3 |
| Vladyslav Kosmach.png | 1ckFmOHGLa9ElBNhgd7-6d_936vcbD5ic |
| Stepan Tabaka.png | 1ljgmST0jC1zchQXNfkDl4rLBJbsDrQmJ |
