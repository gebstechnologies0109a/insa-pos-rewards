package com.epayplus.v2.data.remote

/**
 * Placeholder for future MQTT real-time channel (HiveMQ-compatible).
 * Heartbeat + command polling is the default transport until MQTT is configured.
 */
interface MqttTransport {
    fun connect(brokerUrl: String, clientId: String)
    fun disconnect()
    fun isConnected(): Boolean
    fun publish(topic: String, payload: String)
    fun subscribe(topic: String, onMessage: (String) -> Unit)
}

class MqttTransportStub : MqttTransport {
    override fun connect(brokerUrl: String, clientId: String) { /* not configured */ }
    override fun disconnect() {}
    override fun isConnected(): Boolean = false
    override fun publish(topic: String, payload: String) {}
    override fun subscribe(topic: String, onMessage: (String) -> Unit) {}
}
