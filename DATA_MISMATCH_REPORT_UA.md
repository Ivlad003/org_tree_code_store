# Звіт про невідповідності даних: staff.json vs files.json

**Дата створення:** 2025-12-17
**Загальна кількість співробітників:** 58 (без категорій-заголовків)
**Загальна кількість файлів зображень:** 54 (включаючи логотип)

---

## Короткий огляд

| Категорія | Кількість | Пріоритет |
|-----------|-----------|-----------|
| Співробітники без фото | 7 | ВИСОКИЙ |
| Зайві фото (немає співробітника) | 1 | СЕРЕДНІЙ |
| Невідповідність написання імен | 3 | СЕРЕДНІЙ |
| Проблеми з форматом файлів | 5 | СЕРЕДНІЙ |
| Зайві пробіли в іменах | 4 | НИЗЬКИЙ |
| Дублікати зображень | 1 | НИЗЬКИЙ |
| Невалідні формати файлів | 2 | ВИСОКИЙ |

---

## 1. Співробітники БЕЗ фото (7 осіб) - ВИСОКИЙ ПРІОРИТЕТ

Ці співробітники не мають фото в Google Drive:

| # | Ім'я співробітника | Необхідна дія |
|---|-------------------|---------------|
| 1 | **Andrii Slynchuk** | Завантажити фото як `Andrii Slynchuk.jpg` |
| 2 | **Denys Davydov** | Завантажити фото як `Denys Davydov.jpg` |
| 3 | **Denys Ukhach** | Завантажити фото як `Denys Ukhach.jpg` |
| 4 | **Diana Murdasova** | Завантажити фото як `Diana Murdasova.jpg` |
| 5 | **Mykhailo Marchuk** | Завантажити фото як `Mykhailo Marchuk.jpg` |
| 6 | **Paul Smith** | Завантажити фото як `Paul Smith.jpg` |
| 7 | **Vitalii Kenyiz** | Завантажити фото як `Vitalii Kenyiz.jpg` |

### Рекомендація
Запросити фото у цих 7 співробітників та завантажити до папки "For Corporate Tree" в Google Drive.

---

## 2. Зайві фото (немає відповідного співробітника) - СЕРЕДНІЙ ПРІОРИТЕТ

Ці зображення існують, але не мають відповідного співробітника:

| # | Назва файлу | Google Drive ID | Необхідна дія |
|---|-------------|-----------------|---------------|
| 1 | `Alla Krukovska.jpg` | `1AYF5bIjE6WJSg_-txkEAU4-OVwmc6Jhx` | Додати до staff.json АБО видалити з Drive |
| 2 | `logo.png` | `18DpEoimVPqQHZ1zxtONtgAakf6wUHjUZ` | Залишити (логотип компанії) |

### Рекомендація
- Перевірити, чи Alla Krukovska є поточним співробітником. Якщо так - додати до списку. Якщо ні - архівувати зображення.

---

## 3. Невідповідність написання імен - СЕРЕДНІЙ ПРІОРИТЕТ

Імена, які не збігаються точно між списком співробітників та файлами зображень:

| # | Ім'я в staff | Назва файлу | Проблема | Рекомендація |
|---|--------------|-------------|----------|--------------|
| 1 | `Valeriia Smoliak` | `Valeria Smoliak.jpg` | Відсутня 'i' у файлі | Перейменувати на `Valeriia Smoliak.jpg` |
| 2 | `Zenyk (Zinovii) Haiduk` | `Zinovii Haiduk.jpg` | В staff є прізвисько | Перейменувати на `Zenyk Haiduk.jpg` АБО оновити ім'я в staff на `Zinovii Haiduk` |
| 3 | `Yelyzaveta Sidorenko` | `Yelyzaveta Sidorenko 2.jpg` | Зайвий суфікс " 2" | Перейменувати на `Yelyzaveta Sidorenko.jpg` |

### Рекомендація
Стандартизувати конвенцію найменування:
- **Варіант А:** Перейменувати файли зображень відповідно до імен співробітників
- **Варіант Б:** Оновити n8n workflow для обробки цих варіацій (вже реалізовано)

---

## 4. Проблеми з форматом назв файлів - СЕРЕДНІЙ ПРІОРИТЕТ

Файли з проблемами форматування, які можуть спричинити помилки співставлення:

| # | Поточна назва | Проблема | Виправлена назва |
|---|---------------|----------|------------------|
| 1 | `Baptiste Rucin].jpg` | Зайва дужка `]` | `Baptiste Rucin.jpg` |
| 2 | `Ruslana Chmut .jpeg` | Пробіл перед розширенням | `Ruslana Chmut.jpeg` |
| 3 | `Oksana Shmiliak .png` | Пробіл перед розширенням | `Oksana Shmiliak.png` |
| 4 | `Artem Tkachov.pdf` | PDF, не зображення | Конвертувати в `Artem Tkachov.jpg` |
| 5 | `Daniil Rosomakha.HEIC` | HEIC не підтримується веб | Конвертувати в `Daniil Rosomakha.jpg` |

### Рекомендація
1. Перейменувати файли для видалення помилок та зайвих пробілів
2. Конвертувати невеб-формати (PDF, HEIC) в JPG або PNG

---

## 5. Зайві пробіли в іменах співробітників - НИЗЬКИЙ ПРІОРИТЕТ

Імена співробітників із зайвими пробілами в staff.json:

| # | Поточне ім'я | Пробіли | Виправлене ім'я |
|---|--------------|---------|-----------------|
| 1 | `"Dimitri Damotte "` | 1 в кінці | `"Dimitri Damotte"` |
| 2 | `"Oleksandr Anpilov  "` | 2 в кінці | `"Oleksandr Anpilov"` |
| 3 | `"Ruslana Chmut "` | 1 в кінці | `"Ruslana Chmut"` |
| 4 | `"Valeriia Smoliak "` | 1 в кінці | `"Valeriia Smoliak"` |

### Рекомендація
Очистити вихідну Google таблицю від зайвих пробілів в іменах.

---

## 6. Дублікати зображень - НИЗЬКИЙ ПРІОРИТЕТ

Файли, які з'являються кілька разів:

| Назва файлу | Кількість | Google Drive ID |
|-------------|-----------|-----------------|
| `Victoria Buhai.jpg` | 2 | `10wTrfWWu-vNo27Wg6lG5bSNGg-eCfjZe`, `1nrMZ-A8ARVQHhzNym7tj45SEXgvo0wQ1` |

### Рекомендація
Залишити зображення кращої якості та видалити дублікат.

---

## 7. Невалідні формати файлів - ВИСОКИЙ ПРІОРИТЕТ

Файли, які не можуть відображатися в вебі:

| # | Назва файлу | Формат | Проблема | Рішення |
|---|-------------|--------|----------|---------|
| 1 | `Artem Tkachov.pdf` | PDF | Не формат зображення | Запросити справжнє фото або витягти зображення з PDF |
| 2 | `Daniil Rosomakha.HEIC` | HEIC | Формат Apple, не веб-сумісний | Конвертувати в JPG через онлайн-конвертер або macOS Preview |

### Рекомендація
Конвертувати ці файли в стандартні веб-формати (JPG, PNG або WebP).

---

## Повний статус співставлення

### Успішно співставлені (47 співробітників з фото)

| Ім'я співробітника | Файл зображення | Статус |
|--------------------|-----------------|--------|
| Abilkaiyr Tursunov | Abilkaiyr Tursunov.jpg | OK |
| Aleksandr Shumenko | Aleksandr Shumenko.jpg | OK |
| Alyona Rapova | Alyona Rapova.jpg | OK |
| Andrii Polishchuk | Andrii Polishchuk.jpg | OK |
| Andrii Rachynskyi | Andrii Rachynskyi.jpg | OK |
| Anna-Maria Polovykh | Anna-Maria Polovykh.jpg | OK |
| Artem Tkachov | Artem Tkachov.pdf | ПРОБЛЕМА ФОРМАТУ |
| Arthur Murauskas | Arthur Murauskas.png | OK |
| Baptiste Rucin | Baptiste Rucin].jpg | ПОМИЛКА В НАЗВІ |
| Bertrand Merle | Bertrand Merle.png | OK |
| Bogdan Fedorko | Bogdan Fedorko.jpg | OK |
| Daniil Rosomakha | Daniil Rosomakha.HEIC | ПРОБЛЕМА ФОРМАТУ |
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
| Oksana Shmiliak | Oksana Shmiliak .png | ПРОБІЛ У НАЗВІ |
| Oleksandr Anpilov | Oleksandr Anpilov.jpg | OK |
| Oleksandr Borodavchenko | Oleksandr Borodavchenko.jpg | OK |
| Oleksandr Timoshchenko | Oleksandr Timoshchenko.jpg | OK |
| Olena Ilina | Olena Ilina.jpg | OK |
| Olena Naidenko | Olena Naidenko.jpg | OK |
| Olena Sevastian | Olena Sevastian.jpg | OK |
| Olga Zviezdicheva | Olga Zviezdicheva.jpg | OK |
| Ruslan Mamedov | Ruslan Mamedov.jpg | OK |
| Ruslana Chmut | Ruslana Chmut .jpeg | ПРОБІЛ У НАЗВІ |
| Ryan Chiesi | Ryan Chiesi.jpg | OK |
| Serhii Soloviov | Serhii Soloviov.jpg | OK |
| Stepan Tabaka | Stepan Tabaka.png | OK |
| Tymur Kabulov | Tymur Kabulov.png | OK |
| Valeriia Smoliak | Valeria Smoliak.jpg | НЕВІДПОВІДНІСТЬ НАПИСАННЯ |
| Victoria Buhai | Victoria Buhai.jpg | OK (є дублікат) |
| Vladyslav Kosmach | Vladyslav Kosmach.png | OK |
| Volodymyr Lysenko | Volodymyr Lysenko.jpg | OK |
| Yelyzaveta Sidorenko | Yelyzaveta Sidorenko 2.jpg | СУФІКС У НАЗВІ |
| Zenyk (Zinovii) Haiduk | Zinovii Haiduk.jpg | НЕВІДПОВІДНІСТЬ ПРІЗВИСЬКА |
| Zhanna Chaikovska | Zhanna Chaikovska.JPEG | OK |
| Zhanna Pichkurova | Zhanna Pichkurova.png | OK |

---

## Чек-лист дій

### Негайно (ВИСОКИЙ пріоритет)
- [ ] Завантажити фото для 7 співробітників без фото
- [ ] Конвертувати `Artem Tkachov.pdf` в JPG
- [ ] Конвертувати `Daniil Rosomakha.HEIC` в JPG

### Короткострокові (СЕРЕДНІЙ пріоритет)
- [ ] Перейменувати `Baptiste Rucin].jpg` на `Baptiste Rucin.jpg`
- [ ] Перейменувати `Ruslana Chmut .jpeg` на `Ruslana Chmut.jpeg`
- [ ] Перейменувати `Oksana Shmiliak .png` на `Oksana Shmiliak.png`
- [ ] Перейменувати `Valeria Smoliak.jpg` на `Valeriia Smoliak.jpg`
- [ ] Перейменувати `Yelyzaveta Sidorenko 2.jpg` на `Yelyzaveta Sidorenko.jpg`
- [ ] Визначитись з конвенцією Zenyk/Zinovii
- [ ] Перевірити, чи Alla Krukovska є поточним співробітником

### Підтримка (НИЗЬКИЙ пріоритет)
- [ ] Видалити зайві пробіли з імен у вихідній таблиці
- [ ] Видалити дублікат зображення Victoria Buhai
- [ ] Очистити staff.json (видалити ~50x дубльовані дані)

---

## Технічні примітки

### Оновлення n8n Workflow
N8n workflow було оновлено для обробки:
- Варіацій написання (Valeriia/Valeria тощо)
- Імен з дужками як "Zenyk (Zinovii) Haiduk"
- Нумерованих назв файлів як "Name 2.jpg"
- Зайвих пробілів в іменах
- Невалідних форматів файлів (PDF виключено)

### Виправлення app.js
JavaScript веб-сайту було оновлено для:
- Фільтрації порожніх рядків
- Виправлення імен з комами
- Виправлення невідповідності категорій QA/BDR

---

## Довідка по файлах

| Файл | Google Drive ID |
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
| Victoria Buhai.jpg (дубл) | 1nrMZ-A8ARVQHhzNym7tj45SEXgvo0wQ1 |
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
