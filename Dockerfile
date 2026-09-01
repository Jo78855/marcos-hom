FROM node:22-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build \
    && grep -Rqs '\.site-shell' dist/assets/*.css \
    && test -s dist/coffee/white-lightwood.webp \
    && test -s dist/coffee/honey-wood.webp \
    && test -s dist/fire/main-product.webp

FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s CMD wget -qO- http://127.0.0.1/ || exit 1
