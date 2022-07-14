#!/bin/bash

mkdir /usr/local/share/ca-certificates/extra
echo "$ELASTICSEARCH_CA_CERT" > /usr/local/share/ca-certificates/extra/helfi-elasticsearch.crt
update-ca-certificates
