typedef struct {
    char* source;
    char* name;
    int port;
} Device;

typedef struct {
    char* webURL;
    char* database;
    char* dataRootDir;
    char* dataBaseDir;
    Device* devices;
    int deviceCount;
} AppConfig;

AppConfig* load_config(const char *filename);

int print_config(AppConfig* config);