FROM solr:8.6
USER root

RUN mkdir /solr-conf
COPY .solr/core /solr-conf/conf/

COPY docker/solr/start.sh /solr-conf/
RUN chmod +x /solr-conf/start.sh

USER solr
CMD ["/solr-conf/start.sh"]
