{
    "name": "app/worker-{worker|kebab}",
    "description": "The {worker} worker: queued work that runs interpreted, for libraries that do not compile.",
    "license": "proprietary",
    "type": "project",
    "require": {
        "php": "^8.5",
        "ignibyte/aic-framework": "^0.1"
    },
    "autoload": {
        "psr-4": {
            "{psr4}": "src/"
        }
    },
    "config": {
        "sort-packages": true
    }
}
