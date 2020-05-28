FROM nginx:alpine
WORKDIR /var/www
RUN chown -R nginx:nginx /var/log && chmod -R 755 /var/log
CMD ["nginx"]
EXPOSE 80
