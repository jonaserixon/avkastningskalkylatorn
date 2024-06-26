# Avkastningskalkylatorn

### Setup the project
1. Download the repo
2. Run `docker-compose up --build`

### Usage
#### Step 1
You need to export your transactions from Avanza/Nordnet and then put them in the `/imports/avanza/` and `/imports/nordnet/` directories.
In the docker-compose file you can also specify whether to export your result to an csv file or not.

To be able to calculate your current holdings you need to use this template: https://docs.google.com/spreadsheets/d/10dohImvsGkBNfA_qB5EATt3tX01UKdmBozDhD7bMB18/edit?usp=sharing and enter the ISIN for each holding, export this file as csv and then drop it under the `/imports/stock_price` directory.

The reason for the ISIN code is so that the transactions can be properly matched to the correct holding.


#### Step 2
1. Enter the docker container and start a bash session to execute commands.
```
docker exec -it avkastningskalkylatorn /bin/bash
```
2. Run `avk help` to see all of the available commands.
