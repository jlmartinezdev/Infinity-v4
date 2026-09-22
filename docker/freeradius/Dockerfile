FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=America/Asuncion

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        freeradius \
        freeradius-mysql \
        freeradius-utils \
        ca-certificates \
        tzdata \
    && rm -rf /var/lib/apt/lists/*

COPY entrypoint.sh /entrypoint.sh
COPY raddb/sites-available/default /etc/freeradius/3.0/sites-available/infinity
COPY raddb/clients.conf.template /etc/freeradius/3.0/clients.conf.template
COPY raddb/mods-available/sql /etc/freeradius/3.0/mods-available/sql.infinity
COPY raddb/dictionary.infinity /etc/freeradius/3.0/dictionary.infinity
COPY raddb/policy.d/cuota_hotspot /etc/freeradius/3.0/policy.d/cuota_hotspot

RUN sed -i 's/\r$//' /entrypoint.sh \
    && chmod +x /entrypoint.sh

EXPOSE 1812/udp 1813/udp

ENTRYPOINT ["/entrypoint.sh"]
CMD ["-f", "-l", "stdout"]
