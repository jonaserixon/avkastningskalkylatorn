# Avkastningskalkylatorn

### Setup the project
1. Download the repo
2. Run `docker-compose build`
3. Run `docker-compose up`

### How to use
You need to export your transactions from Avanza/Nordnet and then put them in the `/imports/avanza/` and `/imports/nordnet/` directories.
In the docker-compose file you can also specify whether to export your result to an csv file or not.

To be able to calculate your current holdings you need to use this template: https://docs.google.com/spreadsheets/d/10dohImvsGkBNfA_qB5EATt3tX01UKdmBozDhD7bMB18/edit?usp=sharing and enter the ISIN for each holding, export this file as csv and then drop it under the `/imports/stock_price` directory.

### Known issues
- If you have traded the same company but on different listings, this can mess up calculations since it is not possible to differentiate them via the Avanza export.
- Nordnet exports might have to be encoded to UTF-8-BOM. Use NotePad++ or some other tool for this.
