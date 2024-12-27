FROM registry.cn-hongkong.aliyuncs.com/pondong/php:1.0.4
WORKDIR /opt
ARG APP_ENV=prod
ARG CLEARCACHE=0
RUN if [ ! -d /opt/var ]; then \
mkdir /opt/var;\
fi \
&& if [ ! -d /opt/var/log ]; then \
mkdir /opt/var/log; \
fi \
&& if [ ! -d /opt/var/cache ]; then \
mkdir /opt/var/cache;\
fi \
&& if [ ! -d /opt/var/meta ]; then \
mkdir /opt/var/meta;\
fi \
&& if [ ! -d /opt/class ]; then \
mkdir /opt/class;\
fi
RUN chmod 777 /opt/var -R
ADD class/composer.json class/composer.json
ADD class/composer.beta.json class/composer.beta.json
ADD class/composer.phar class/composer.phar
ADD class/auth.json class/auth.json
RUN if [ ${APP_ENV} = "dev" ]; then \
       yes|cp class/composer.beta.json class/composer.json; \
    fi


RUN if [ ${CLEARCACHE} = "1" ]; then \
      php class/composer.phar clearcache; \
    fi
RUN cd /opt/class && php /opt/class/composer.phar  config -g repo.packagist composer https://mirrors.aliyun.com/composer/
RUN cd /opt/class && php /opt/class/composer.phar  -vvv install --no-dev --prefer-dist

ADD . .
STOPSIGNAL SIGQUIT
EXPOSE 9000
CMD ["php-fpm"]
