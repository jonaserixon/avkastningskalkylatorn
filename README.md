# Avkastningskalkylatorn

### Setup the project
1. Download the repo
2. Run `docker-compose build`
3. Run `docker-compose up`

### How to use
You need to export your transactions from Avanza/Nordnet and then put them in the `/imports/avanza/` and `/imports/nordnet/` directories.
In the docker-compose file you can also specify whether to export your result to an csv file or not.

### Known issues
If you have traded the same company but on different listings, this can mess up calculations since it is not possible to differentiate them via the Avanza export.
