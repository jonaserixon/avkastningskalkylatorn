# Avkastningskalkylatorn

### Setup the project
1. Download the repo
2. Run `docker build -t avkastningskalkylatorn .`
3. Run `docker run -it --rm --name <my-running-app> avkastningskalkylatorn`

### Known issues
If you have traded the same company but on different listings, this can mess up calculations since it is not possible to differentiate them via the Avanza export.
