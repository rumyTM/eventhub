FROM node:20-alpine AS deps
WORKDIR /app
COPY package*.json ./
RUN npm ci --only=production

FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json tsconfig.json tsconfig.test.json vitest.config.ts ./
RUN npm ci
COPY src ./src
COPY tests ./tests
RUN npm run build

FROM node:20-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production
COPY --from=build /app/node_modules ./node_modules
COPY --from=build /app/dist ./dist
COPY --from=build /app/src ./src
COPY --from=build /app/tests ./tests
COPY --from=build /app/vitest.config.ts ./
COPY --from=build /app/tsconfig.test.json ./
COPY package.json tsconfig.json ./
RUN mkdir -p logs
EXPOSE 8002
CMD ["node", "dist/index.js"]
