default:
	make run
	docker exec -it --user dwm dwm bash

clean:
	-@docker ps -a -q | xargs docker stop
	-@docker ps -a -q | xargs docker rm
	-@docker volume ls -f dangling=true -q| xargs docker volume rm
	docker images --quiet --filter=dangling=true | xargs --no-run-if-empty docker rmi -f

run:
	export UID=$(id -u)
	export GID=$(id -g)
	docker-compose up --build -d --remove-orphans

wipe:
	docker system prune -a
