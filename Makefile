default:

cs:
	vendor/bin/php-cs-fixer fix

php-cs-dry:
	vendor/bin/php-cs-fixer fix --dry-run --verbose

prepare:
	make cs \
		&& make run-tests \
		&& make stan \
		&& make verify

run-tests:
	vendor/bin/phpunit

stan:
	vendor/bin/phpstan analyse -c phpstan.neon

verify:
	bin/mergeJsonLDFiles
	/home/dwm/apache-jena/bin/shacl validate --data ./knowledge/__merged_knowledge.nt
	bin/verifyProcesses
