SHELL := /bin/bash
COMPOSER_BIN = composer


build:
	"$(COMPOSER_BIN)" install -o --no-dev -q

init-dev:
	"$(COMPOSER_BIN)" install -q

tests: init-dev
	bin/codecept run

auto-test: init-dev
	@echo "Watch files for changes. Hit CTRL+C to quit."
	@inotifywait -e create -e modify -e delete -e moved_to -e move_self -m -r -q --format '%w%f' src/ tests/ | while read FILE; do \
		if [[ "$${FILE: -4}" != ".php" ]]; then continue; fi;\
		echo "Change detected on \"$${FILE}\"…"; \
		bin/codecept run; \
    done
