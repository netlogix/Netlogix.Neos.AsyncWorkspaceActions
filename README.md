# Netlogix.Neos.AsyncWorkspaceActions

This package that allows asynchronous execution of workspace actions (e.g. publishing) through the default Neos UI.

When publishing large amounts of nodes, the PHP process may run in the configured memory_limit, thus failing to
publish a workspace. This package aims to work around this problem by moving the publishing to a subprocess that
may use a different PHP configuration with unlimited memory_limit.

## Installation

`composer require netlogix/neos-asyncworkspaceactions`

## Configuration

You can configure the threshold after which asynchronous publishing should trigger. 
If less nodes are published, the default Neos behaviour is used. 

```yaml
Netlogix:
  Neos:
    AsyncWorkspaceActions:
      # Amount of nodes required to publish or discard asynchronously
      nodeThreshold: 100
```

