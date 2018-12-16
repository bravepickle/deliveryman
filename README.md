# deliveryman
Deliveryman is an agent that processes requests to your application(s) in batches sequentially or in parallel in simplified manner.

## Description
This client library's main purpose is to make it easy for applications to aggregate multiple HTTP requests in single batch, process them sequentially or in parallel, then send response back to sender. 

It is designed the way it can be used independently from your main application with communication being used over data providers, usually over HTTP proxy requests. Also it is easy to expand and configure behavior of the library.

Especially is useful if REST-like APIs used on backend.

### General use case scenarios
* Using application as API Gateway for your microservices. Is good both for syncing data over REST platform architecture and sending from client multiple requests simultaneously
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
* Avoid data inconsistencies. You can delay updates of your data until you are sure all requests were processed correctly

## Security
Attention! Configs should be properly defined! Do not forget to whitelist allowed domains, protocols, routing and similar that should be available for *Deliveryman* to handle. Otherwise it can be misused due to security breaches and used as proxy for shady requests. It is important to remember before going to production.

## Features

## Concept

In this section we'll talk about concepts that were used for this library, terms definitions etc.

### Dictionary
*Agent* - an entity that is authorized to act on behalf of another (called the *principal*) to create 
relations with a *third party*. This is what an application integrated with *Deliveryman* library is doing.

*Principal*, *client* - is an entity, who authorizes an *agent* to act to communicate with a *third party*. 
Principals examples: web application, JS web client in browser, another web server, mobile application, scripts. 

*Third party*, *receiver* - a target entity, which *principal* wishes to interact with. 
Third parties examples: remote web server, database, file storage, message queue server, web chats, same web server's other endpoints (used for aggregations).

*Communication channel*, *channel* - API, medium for transmitting data from *agent* to *third party*. 
Channels examples: HTTP API, message queue API, file storage API, SQL queries, any other API for interaction.

*Contract* - a promise or set of promises between *principal*, *agent* and *third parties*. 
Each party to a contract acquires rights and duties relative to the rights and duties of the other parties. 
It describes how *agent* should communicate with *third parties* and how to process results before sending 
them back to principal. In our case a contract is a batch request's body that sends from *client* to *agent* 
with list of requests and settings.

*Queue* - ordered requests queue, that must be run sequentially. Request fail may terminate the rest of the queue.

*Batch request* - an entity that contains contract definition for given set of requests. It is sent from *principal* to *agent*.

*Batch response* - an entity that is being returned to *principal* from *agent* after processing requests from *batch request*.

### Definition
In theory, everything is rather simple. A principal wants to send some requests in certain order to one or multiple 
third parties. Principal has some expectations on what kind of data can be received for given requests. Instead of 
sending those requests directly, principal uses agent to send them. Principal prepares a set of requests, rules 
and describes how responses should be handled in different scenarios. Prepared document is called contract and it is 
sent within body of batch request it sends to agent. Agent receives batch request together with contract, validates its
contents and, if everything is fine, processes requests according to provided rules, then it returns formatted response
to principal with aggregated results.

Each valid request will be sent from agent to third party over dedicated communication channel defined in configuration. 
Channels prepare requests for usage together with given implementation of PAI, dispatch some events, receive responses 
and format them to the unified manner before sending back to agent higher levels abstractions.

Single batch request contains list of one or more queues of requests. Queues can be processed in parallel asynchronously. 
Within single queue requests are run in sequence synchronously according to provided order. In addition batch request,
may include general settings for this batch of requests and custom ones for each individual request. It makes it 
possible to customize properly contract to fit in better to expectations.

It is possible to extend greatly functionality by using dispatched events hooks mechanism, define your own implementations
of communication APIs and processing.

The following steps should be taken to make agent working properly:
1. Install *Deliveryman* library, use bundle, if using Symfony framework
1. Create endpoint for principal be able to call and specify auth for it, if needed
1. Configure library, according to expectations. Following things should be considered:
    1. Domains allowed to work with
    1. Headers processing rules. Which allowed to be returned to principal? Which could be sent to third party? 
    1. Security considerations: CORS, Cookies support, authorization, authentication 
    1. Performance: profiling, requests rate limit, timeouts, response max body size, maximum number of requests in queue and in total.
    1. Logging requests and errors
    1. What configuration settings should be available for principal to modify?
    1. Which responses and their data should be returned to principal after processing?
1. Use OpenAPI document for understanding API format used for application

## Installation

## Configuration

## Usage Examples

## TODO
- [x] Swagger config documentation.
- [ ] Remove symfony/http-foundation from dependencies if guzzlehttp/psr7 is feasible enough.
- [ ] Authorization: optional, configurable. Provide only with interface and some simple provider - check hardcoded API keys
- [ ] Update usage of Exception classes within library. Must be properly defined
- [ ] Write docs on usage examples
- [ ] Define format for parsing response data: json, csv, csv-to-json, plain text, binary, xml etc.
- [ ] Set default format response parsing data
- [ ] In library config parameter mapping for mapping: Content-Type -> format
- [ ] For binary data return links instead of body to download files by link 
- [ ] Optionally specify to defer response for long running batch requests. Return link for polling results
- [ ] Cleaning downloaded files and deferred responses leave for developers to write 
- [ ] Add to library's config section *providers* with list of providers that can be used, default one and their custom settings
- [ ] Add flag *hideHeaders* in library config - do not return to client any headers
- [ ] Add option *responseHeadersAllow* in library config - allow headers based on config for passing to sender 
- [ ] Add option *requestHeadersAllow* in library config - allow headers based on config for passing to target URLs
- [ ] Add option to limit number of possible queues and requests in queue
- [ ] Only defined in *requestHeadersRules*
- [ ] Why not use JS frontend library instead? When is it better this way?
- [ ] Solve problem of how to pass headers in responses back if needed to be added to batch response, e.g. cookies 
- [ ] Optimize handling queues: single queue - run normally; multiple queues but single request per each - run in parallel;
         multiple queues with various numbers of requests - run in forked scripts or implement queues consumers-receivers, worker-jobs etc.
