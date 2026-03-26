#include "server.h"
#include "config.h"
#include "db_transfer.h"

AppConfig* appConfig;

int main(){

    appConfig = load_config("serverconfig.json");

    print_config(appConfig);

    // load config

    // start all r+w

    for (int i = 0 ; i < appConfig->deviceCount ; i++)
    {
        printf("Init CB\n");
        printf("Add to buffer array\n");
        printf("Start writer and readers\n");
    }

    while(1)
    {
        checkAndUpdateDb(appConfig->database, appConfig->webURL);

        // check and update database

        // check for starting / ending / canceled recordings and inform r+w

        // rewrite the status report file

        sleep(60);
    }

    return 0;
}