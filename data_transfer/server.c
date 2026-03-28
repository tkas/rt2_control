#include "config.h"
#include "db_transfer.h"
#include "CircularBuffer.h"

typedef struct {

    circularBuffer buffer;

    Device deviceInfo;

} BufferSession;

DbItem currentRecording;

AppConfig* appConfig;



int main(){

    appConfig = loadConfig("serverconfig.json");

    printConfig(appConfig);

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

        DbItem nextRecording = getDbItem(appConfig->database);

        int curTime = (int)time(NULL);

        if (nextRecording.id != -1)
        {
            printDbItem(nextRecording);
        }

        if (nextRecording.obs_start_time > curTime)
        {
            sleep(nextRecording.obs_start_time - curTime);

            // add mutex
            currentRecording = nextRecording;

            // notify all circular buffers (recording active)
        }
        else if (nextRecording.rec_start_time > curTime)
        {

        }
        else
        {

        }

        // check for starting / ending / canceled recordings and inform r+w

        // rewrite the status report file

        sleep(60);
    }

    return 0;
}