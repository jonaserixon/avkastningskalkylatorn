![](https://img.shields.io/badge/dynamic/json?url=https://gist.githubusercontent.com/jonaserixon/c2588864dd9a54c8540f244a87732c7e/raw/phpstan_badge.json&label=phpstan&query=$.message&color=blue&style=flat)

# Avkastningskalkylatorn

### Setup the project
1. Download the repo
2. Run `docker-compose up --build`

### Features
- Parse transactions from Avanza, Nordnet or custom format.
- Get details such as absolute returns, fees, taxes, purchase value and more.
- Dividend and investment overview reports.
- Calculate TWR (Time-Weighted Rate of Return).
- Export transactions to [Portfolio Performance](https://github.com/portfolio-performance/portfolio) format.
- Filter and calculate based on transactions, assets, dates etc. with support of exporting the results to csv format.

### Usage
More info to be added.

1. Enter the docker container and start a bash session to execute commands.
```
docker exec -it avkastningskalkylatorn /bin/bash
```
2. Run `avk help` to see all of the available commands.
