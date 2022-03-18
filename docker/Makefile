default:
	docker build . -t dwm \
	&& \
	docker run -it \
			   -v $(PWD)/../:/var/www/html \
			   --name dwm \
			   --user dwm \
			   dwm \
			   /bin/bash

clean:
	-@docker ps -a -q | xargs docker stop
	-@docker ps -a -q | xargs docker rm
	-@docker volume ls -f dangling=true -q| xargs docker volume rm
	docker images --quiet --filter=dangling=true | xargs --no-run-if-empty docker rmi -f

wipe:
	docker system prune -a
