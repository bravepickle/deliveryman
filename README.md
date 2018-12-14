# deliveryman
Deliveryman is an agent that processes requests to your application(s) in batches sequentially or in parallel in simplified manner.

## Description
This client library's main purpose is to make it easy for applications to aggregate multiple HTTP requests in single batch, process them sequentially or in parallel, then send response back to sender. 

It is designed the way it can be used independently from your main application with communication being used over data providers, usually over HTTP proxy requests. Also it is easy to expand and configure behavior of the library.

Especially is useful if REST-like APIs used on backend.

### General use case scenarios
* Using application as API Gateway for your microservices.
* Solving problems with complexity received due to myriad async requests sent from clients, such as frontend (browser, mobile) apps.
* Simplifying work with Single Page Applications due to its ability to solve headache for frontend developers to rule out multiple use cases of crashing requests when sending simultaneously.
* Simple proxy implementation, if for some reason direct requests cannot be handled (e.g. firewall)
* Possible performance improvements, if *Deliveryman* is positioned closely to target domains. Will save on networking communications delays.
* Make requests in proper order to eliminate unexpected results when older requests outrun newer ones due to networking specifics.
* Use it as middleware to log, transform or listen for all sent requests over *Deliveryman*.
* Can be used as serverless function due to its loose coupling to applications and small library size.
* Less memory consumption compared to processing multiple requests during single call, avoiding side effects
* Easier to implement than GraphQL and similar approaches, especially in old applications
* Almost no imprint is left after integration given approach to existing code base
* Can be used to skip cross-origin policy limitations, if configured to allow this behavior
* Avoid uncontrollable race of request handling calls

## Security
Attention! Configs should be properly defined! Do not forget to whitelist allowed domains, protocols, routing and similar that should be available for *Deliveryman* to handle. Otherwise it can be misused due to security breaches and used as proxy for shady requests. It is important to remember before going to production.

## Features

## Installation

## Configuration

## Usage Examples

## TODO
- [x] Swagger config documentation.
- [ ] Remove symfony/http-foundation from dependencies if guzzlehttp/psr7 is feasible enough.
- [ ] Authorization: optional, configurable. Provide only with interface and some simple provider - check hardcoded API keys
- [ ] Update usage of Exception classes within library. Must be properly defined
- [ ] Write docs on usage examples
